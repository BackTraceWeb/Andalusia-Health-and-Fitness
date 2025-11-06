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
