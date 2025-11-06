<?php
/**
 * Process Membership Signup
 * Inserts new member into database and sends email to staff
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_bootstrap.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['first_name'], $data['last_name'], $data['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required member information']);
    exit;
}

try {
    $pdo = pdo();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Calculate valid_until date (30 days from now)
    $validUntil = date('Y-m-d', strtotime('+30 days'));

    // Determine payment type (table ENUM: 'draft','card','cash','other')
    $paymentType = $data['waive_initiation'] ? 'draft' : 'card';

    // Insert member into database
    // Note: Only using columns that exist in the members table
    $stmt = $pdo->prepare("
        INSERT INTO members (
            first_name,
            last_name,
            email,
            zip,
            monthly_fee,
            payment_type,
            status,
            valid_until
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['first_name'],
        $data['last_name'],
        $data['email'],
        $data['zip'] ?? '',
        $data['plan_amount'],
        $paymentType,
        'current',
        $validUntil
    ]);

    $memberId = (int)$pdo->lastInsertId();

    // Log the signup
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents(
        "$logDir/membership-signups.log",
        date('Y-m-d H:i:s') . " - New member #{$memberId}: {$data['first_name']} {$data['last_name']} ({$data['email']})\n",
        FILE_APPEND
    );

    // Send email to staff
    sendMembershipEmail($data, $memberId);

    // Send welcome email to member
    sendMemberWelcomeEmail($data, $memberId);

    echo json_encode([
        'success' => true,
        'memberId' => $memberId,
        'message' => 'Membership created successfully'
    ]);

} catch (Throwable $e) {
    error_log("Membership signup error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to create membership: ' . $e->getMessage()
    ]);
}

/**
 * Send membership email to staff
 */
function sendMembershipEmail(array $data, int $memberId): void
{
    try {
        // Build email content
        $subject = "New Membership Signup: {$data['first_name']} {$data['last_name']}";

        $html = buildEmailHTML($data, $memberId);
        $text = buildEmailText($data, $memberId);

        // Get access token from Microsoft Graph
        $token = getMicrosoftGraphToken();

        // Send email via Microsoft Graph API
        sendGraphEmail($token, $subject, $html, $text, $data);

    } catch (Throwable $e) {
        error_log("Email error: " . $e->getMessage());
        // Don't fail the entire signup if email fails
    }
}

/**
 * Send welcome email to new member
 */
function sendMemberWelcomeEmail(array $data, int $memberId): void
{
    try {
        if (empty($data['email'])) {
            return; // No email provided
        }

        $subject = "Welcome to Andalusia Health & Fitness!";
        $html = buildMemberWelcomeHTML($data, $memberId);
        $text = buildMemberWelcomeText($data, $memberId);

        // Get access token from Microsoft Graph
        $token = getMicrosoftGraphToken();

        // Send to member (not staff)
        sendGraphEmailToMember($token, $subject, $html, $text, $data);

    } catch (Throwable $e) {
        error_log("Member welcome email error: " . $e->getMessage());
        // Don't fail the entire signup if email fails
    }
}

function getMicrosoftGraphToken(): string
{
    $graphConfig = config('graph');
    $tenant = $graphConfig['tenant_id'];
    $clientId = $graphConfig['client_id'];
    $clientSecret = $graphConfig['client_secret'];

    $tokenUrl = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token";

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default'
        ])
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to get Microsoft Graph token');
    }

    return $tokenData['access_token'];
}

function sendGraphEmail(string $token, string $subject, string $html, string $text, array $data): void
{
    $graphConfig = config('graph');
    $sender = $graphConfig['sender_upn'];
    $recipient = $graphConfig['to'];
    $ccMember = $graphConfig['cc_member'] ?? false;

    $message = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $recipient
                    ]
                ]
            ]
        ],
        'saveToSentItems' => true
    ];

    // Optionally CC the member
    if ($ccMember && !empty($data['email'])) {
        $message['message']['ccRecipients'] = [
            [
                'emailAddress' => [
                    'address' => $data['email'],
                    'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')
                ]
            ]
        ];
    }

    $ch = curl_init("https://graph.microsoft.com/v1.0/users/$sender/sendMail");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($message)
    ]);

    curl_exec($ch);
    curl_close($ch);
}

function buildEmailHTML(array $data, int $memberId): string
{
    $waiverCount = count($data['waivers'] ?? []);
    $monthlyTotal = number_format((float)$data['monthly_total'], 2);
    $todayTotal = number_format((float)$data['today_total'], 2);

    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2 style='color: #d81b60;'>New Membership Signup</h2>

        <h3>Member Information</h3>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr><td><strong>Member ID:</strong></td><td>#{$memberId}</td></tr>
            <tr><td><strong>Name:</strong></td><td>{$data['first_name']} {$data['last_name']}</td></tr>
            <tr><td><strong>Email:</strong></td><td>{$data['email']}</td></tr>
            <tr><td><strong>Phone:</strong></td><td>" . ($data['phone'] ?? 'Not provided') . "</td></tr>
            <tr><td><strong>Address:</strong></td><td>" . ($data['address1'] ?? '') . ($data['city'] ? ", {$data['city']}" : '') . ($data['state'] ? ", {$data['state']}" : '') . " " . ($data['zip'] ?? '') . "</td></tr>
        </table>

        <h3>Membership Details</h3>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr><td><strong>Plan:</strong></td><td>{$data['plan']}</td></tr>
            <tr><td><strong>Monthly Dues:</strong></td><td>\${$monthlyTotal}</td></tr>
            <tr><td><strong>Payment Type:</strong></td><td>" . ($data['waive_initiation'] ? 'Draft (Auto-pay)' : 'Manual') . "</td></tr>
            <tr><td><strong>Fobs:</strong></td><td>{$data['fob_count']}</td></tr>
            <tr><td><strong>Tanning:</strong></td><td>" . ($data['add_tanning'] ? 'Yes' : 'No') . "</td></tr>
            <tr><td><strong>Waivers Signed:</strong></td><td>{$waiverCount}</td></tr>
        </table>

        <h3>Signed Waivers</h3>
        " . buildWaiverHTML($data['waivers'] ?? []) . "

        <h3>Payment</h3>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr><td><strong>Today's Total:</strong></td><td>\${$todayTotal}</td></tr>
            <tr><td><strong>Invoice:</strong></td><td>{$data['invoice']}</td></tr>
            <tr><td><strong>Status:</strong></td><td style='color: green;'>✓ Paid</td></tr>
        </table>

        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Program fobs for member</li>
            <li>Member should come in to pick up fobs</li>
            <li>" . ($data['waive_initiation']
                ? 'Set up draft payment in system'
                : 'Member will pay manually each month via <a href=\"https://andalusiahealthandfitness.com/quickpay/\" style=\"color: #d81b60;\">QuickPay</a>') . "</li>
        </ul>
    </body>
    </html>
    ";
}

function buildEmailText(array $data, int $memberId): string
{
    $monthlyTotal = number_format((float)$data['monthly_total'], 2);
    $todayTotal = number_format((float)$data['today_total'], 2);

    $phone = $data['phone'] ?? 'Not provided';
    $address = ($data['address1'] ?? '') . ($data['city'] ? ", {$data['city']}" : '') . ($data['state'] ? ", {$data['state']}" : '') . " " . ($data['zip'] ?? '');
    $address = trim($address) ?: 'Not provided';

    return "
NEW MEMBERSHIP SIGNUP

Member ID: #{$memberId}
Name: {$data['first_name']} {$data['last_name']}
Email: {$data['email']}
Phone: {$phone}
Address: {$address}

MEMBERSHIP DETAILS
Plan: {$data['plan']}
Monthly Dues: \${$monthlyTotal}
Payment Type: " . ($data['waive_initiation'] ? 'Draft (Auto-pay)' : 'Manual') . "
Fobs: {$data['fob_count']}
Tanning: " . ($data['add_tanning'] ? 'Yes' : 'No') . "

SIGNED WAIVERS
" . buildWaiverText($data['waivers'] ?? []) . "

PAYMENT
Today's Total: \${$todayTotal}
Invoice: {$data['invoice']}
Status: PAID

NEXT STEPS:
- Program fobs for member
- Member should come in to pick up fobs
" . ($data['waive_initiation']
    ? "- Set up draft payment in system"
    : "- Member will pay manually each month via QuickPay: https://andalusiahealthandfitness.com/quickpay/") . "
    ";
}

function sendGraphEmailToMember(string $token, string $subject, string $html, string $text, array $data): void
{
    $graphConfig = config('graph');
    $sender = $graphConfig['sender_upn'];

    $message = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $data['email'],
                        'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')
                    ]
                ]
            ]
        ],
        'saveToSentItems' => true
    ];

    $ch = curl_init("https://graph.microsoft.com/v1.0/users/$sender/sendMail");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($message)
    ]);

    curl_exec($ch);
    curl_close($ch);
}

function buildMemberWelcomeHTML(array $data, int $memberId): string
{
    $monthlyTotal = number_format((float)($data['monthly_total'] ?? 0), 2);
    $firstName = htmlspecialchars($data['first_name']);
    $plan = htmlspecialchars($data['plan']);

    $paymentInfo = $data['waive_initiation']
        ? "<p>Your monthly dues of <strong>\${$monthlyTotal}</strong> will be automatically drafted from your account.</p>"
        : "<p>Your monthly dues are <strong>\${$monthlyTotal}</strong>. You can pay online anytime using our <a href=\"https://andalusiahealthandfitness.com/quickpay/\" style=\"color: #d81b60; font-weight: bold;\">QuickPay portal</a>.</p>";

    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #000; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: #d81b60; margin: 0;'>Welcome to AHF!</h1>
            </div>

            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p>Hi {$firstName},</p>

                <p>Thank you for joining <strong>Andalusia Health & Fitness</strong>! We're excited to have you as a member.</p>

                <h3 style='color: #d81b60;'>Your Membership Details</h3>
                <ul>
                    <li><strong>Plan:</strong> {$plan}</li>
                    <li><strong>Monthly Dues:</strong> \${$monthlyTotal}</li>
                    <li><strong>Member ID:</strong> #{$memberId}</li>
                </ul>

                <h3 style='color: #d81b60;'>Next Steps</h3>
                <ol>
                    <li><strong>Pick up your key fob</strong> - Stop by the gym to get your 24/7 access key</li>
                    <li><strong>Start working out!</strong> - Your membership is active</li>
                    <li><strong>Monthly payments</strong> - {$paymentInfo}</li>
                </ol>

                <h3 style='color: #d81b60;'>Gym Information</h3>
                <p>
                    <strong>Address:</strong> 205 Church St, Andalusia, AL 36420<br>
                    <strong>Office Hours:</strong> Mon-Thu 8am-5pm, Fri 8am-12pm<br>
                    <strong>Gym Access:</strong> 24/7 with your key fob<br>
                    <strong>Phone:</strong> (334) 582-2000
                </p>

                <p style='margin-top: 30px;'>If you have any questions, feel free to call us or stop by!</p>

                <p style='margin-top: 20px;'>
                    <strong>Welcome to the AHF family!</strong><br>
                    <em style='color: #666;'>Andalusia Health & Fitness Team</em>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function buildMemberWelcomeText(array $data, int $memberId): string
{
    $monthlyTotal = number_format((float)($data['monthly_total'] ?? 0), 2);
    $firstName = $data['first_name'];
    $plan = $data['plan'];

    $paymentInfo = $data['waive_initiation']
        ? "Your monthly dues of \${$monthlyTotal} will be automatically drafted from your account."
        : "Your monthly dues are \${$monthlyTotal}. You can pay online anytime using our QuickPay portal: https://andalusiahealthandfitness.com/quickpay/";

    return "
Hi {$firstName},

Thank you for joining Andalusia Health & Fitness! We're excited to have you as a member.

YOUR MEMBERSHIP DETAILS
- Plan: {$plan}
- Monthly Dues: \${$monthlyTotal}
- Member ID: #{$memberId}

NEXT STEPS
1. Pick up your key fob - Stop by the gym to get your 24/7 access key
2. Start working out! - Your membership is active
3. Monthly payments - {$paymentInfo}

GYM INFORMATION
Address: 205 Church St, Andalusia, AL 36420
Office Hours: Mon-Thu 8am-5pm, Fri 8am-12pm
Gym Access: 24/7 with your key fob
Phone: (334) 582-2000

If you have any questions, feel free to call us or stop by!

Welcome to the AHF family!
Andalusia Health & Fitness Team
    ";
}

function buildWaiverHTML(array $waivers): string
{
    if (empty($waivers)) {
        return "<p style='color: #999;'>No waivers recorded</p>";
    }

    $html = "<table style='border-collapse: collapse; width: 100%; border: 1px solid #ddd;'>";
    $html .= "<tr style='background: #f0f0f0;'>
                <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Name</th>
                <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Date of Birth</th>
                <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Signature</th>
                <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Date Signed</th>
              </tr>";

    foreach ($waivers as $index => $waiver) {
        $name = htmlspecialchars($waiver['firstName'] ?? '') . ' ' . htmlspecialchars($waiver['lastName'] ?? '');
        $dob = htmlspecialchars($waiver['dob'] ?? 'N/A');
        $signature = htmlspecialchars($waiver['signature'] ?? 'N/A');
        $dateSigned = !empty($waiver['timestamp']) ? date('m/d/Y g:i A', strtotime($waiver['timestamp'])) : 'N/A';

        $html .= "<tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$name}</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$dob}</td>
                    <td style='padding: 10px; border: 1px solid #ddd; font-style: italic;'>{$signature}</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$dateSigned}</td>
                  </tr>";
    }

    $html .= "</table>";
    return $html;
}

function buildWaiverText(array $waivers): string
{
    if (empty($waivers)) {
        return "No waivers recorded\n";
    }

    $text = "";
    foreach ($waivers as $index => $waiver) {
        $num = $index + 1;
        $name = ($waiver['firstName'] ?? '') . ' ' . ($waiver['lastName'] ?? '');
        $dob = $waiver['dob'] ?? 'N/A';
        $signature = $waiver['signature'] ?? 'N/A';
        $dateSigned = !empty($waiver['timestamp']) ? date('m/d/Y g:i A', strtotime($waiver['timestamp'])) : 'N/A';

        $text .= "Waiver #{$num}:\n";
        $text .= "  Name: {$name}\n";
        $text .= "  DOB: {$dob}\n";
        $text .= "  Signature: {$signature}\n";
        $text .= "  Date Signed: {$dateSigned}\n\n";
    }

    return $text;
}
