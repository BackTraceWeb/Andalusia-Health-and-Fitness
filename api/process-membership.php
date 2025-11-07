<?php
/**
 * Process Membership Signup
 * Inserts new member into database and sends email to staff
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';
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

        // Generate waiver PDFs
        $waiverPDFs = generateWaiverPDFs($data);

        // Send email via Microsoft Graph API with waiver attachments
        sendGraphEmail($token, $subject, $html, $text, $data, $waiverPDFs);

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

function sendGraphEmail(string $token, string $subject, string $html, string $text, array $data, array $attachments = []): void
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

    // Add attachments if provided
    if (!empty($attachments)) {
        $message['message']['attachments'] = [];
        foreach ($attachments as $attachment) {
            $message['message']['attachments'][] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['name'],
                'contentType' => 'application/pdf',
                'contentBytes' => $attachment['base64']
            ];
        }
    }

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

function generateWaiverPDFs(array $data): array
{
    $waivers = $data['waivers'] ?? [];
    if (empty($waivers)) {
        return [];
    }

    $attachments = [];

    foreach ($waivers as $index => $waiver) {
        $num = $index + 1;
        $name = ($waiver['firstName'] ?? '') . ' ' . ($waiver['lastName'] ?? '');
        $dob = $waiver['dob'] ?? 'N/A';
        $signature = $waiver['signature'] ?? 'N/A';
        $dateSigned = !empty($waiver['timestamp']) ? date('F j, Y g:i A', strtotime($waiver['timestamp'])) : date('F j, Y g:i A');

        // Build waiver HTML
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; color: #000; }
                h1 { color: #d81b60; text-align: center; margin-bottom: 30px; }
                h2 { color: #333; margin-top: 30px; }
                .info-box { background: #f9f9f9; padding: 15px; border-left: 4px solid #d81b60; margin: 20px 0; }
                .info-box p { margin: 5px 0; }
                .info-box strong { color: #d81b60; }
                .signature-box { border: 2px solid #000; padding: 20px; margin: 30px 0; text-align: center; }
                .signature { font-family: 'Brush Script MT', cursive; font-size: 32px; font-style: italic; }
                .terms { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #fff; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <h1>Andalusia Health & Fitness</h1>
            <h2 style='text-align:center; margin-bottom:30px;'>Membership Agreement & Liability Waiver</h2>

            <div class='info-box'>
                <p><strong>Member Name:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>Date of Birth:</strong> " . htmlspecialchars($dob) . "</p>
                <p><strong>Date Signed:</strong> " . htmlspecialchars($dateSigned) . "</p>
            </div>

            <div class='terms'>
                <h2 style='text-align:center;'>ANDALUSIA HEALTH AND FITNESS LLC</h2>
                <p style='text-align:center;'>205 CHURCH STREET · ANDALUSIA, AL 36420</p>

                <h3>WAIVER, RELEASE OF LIABILITY AND COVENANT-NOT-TO-SUE</h3>
                <p>
                    I UNDERSTAND AND AGREE that Andalusia Health and Fitness LLC is not responsible to any person for
                    any injury or loss of property while that person is practicing, taking class, competing,
                    participating in open gym, or otherwise involved in activities at Andalusia Health and Fitness LLC
                    for any reason whatsoever, including ordinary negligence on the part of Andalusia Health and
                    Fitness LLC, its owners, board of directors, officers, agents, employees, or trainers.
                </p>
                <p>
                    I understand the facility is available 24 hours a day and, during non-staff hours, is an
                    unsupervised facility. I use this facility at my own risk and will supply my own spotters.
                    I am prohibited from allowing any non-member to use the facility or my membership. If I breach
                    this agreement, Andalusia Health and Fitness may charge a fee per unauthorized person and may
                    terminate my membership. I understand that electronic surveillance and other means may be used
                    to enforce this policy.
                </p>
                <p>
                    I understand it is my responsibility to consult my physician before using the facilities and that
                    Andalusia Health and Fitness bears no responsibility for my physical health.
                </p>
                <p>
                    In consideration of access to the facilities, I agree not to sue and release Andalusia Health and
                    Fitness from any and all past, present, or future claims for property damage, personal injury,
                    or wrongful death arising out of my participation in weight-lifting, weight-training, exercise
                    routines, or related activities, including ordinary negligence.
                </p>
                <p>
                    I recognize that weight-training and related activities involve certain risks, including but not
                    limited to serious injury or death. Equipment, safety devices, and spotting may reduce but cannot
                    eliminate these risks. I agree to use all safety equipment properly and understand risks may also
                    arise from the actions of other participants.
                </p>
                <p>
                    This waiver and covenant-not-to-sue is intended to be as broad and inclusive as permitted by
                    Alabama law. If any portion is held invalid, the remainder shall continue in full force and effect.
                    Jurisdiction and venue for any proceedings arising out of this agreement will be within the State
                    of Alabama, County of Covington.
                </p>
                <p>
                    I affirm I am of legal age and am freely signing this agreement. I have read and understand that
                    by signing I am giving up legal rights and/or remedies which may be available for the ordinary
                    negligence of Andalusia Health and Fitness.
                </p>

                <h3>MEMBERSHIP AGREEMENT</h3>
                <ol>
                    <li>Monthly dues are owed per membership selected and continue until cancelled per policy.</li>
                    <li>Initiation fees are non-refundable; memberships are non-transferable and non-voting.</li>
                    <li>Members may bring guests per rules and are responsible for guest conduct and charges.</li>
                    <li>Termination requires proper notice; otherwise dues remain owed until 30 days after notice.</li>
                    <li>Management may suspend or cancel membership for violations; rules may be updated.</li>
                    <li>Member accepts the inherent risks of using club facilities and services.</li>
                </ol>

                <h3>GENERAL RULES</h3>
                <ul>
                    <li>Wear proper gym attire (athletic shoes; no jeans, sandals, boots, or open-toe shoes).</li>
                    <li>Re-rack weights and return benches to their area; wipe down equipment after use.</li>
                    <li>No profanity; limit cardio to 30 minutes when others are waiting.</li>
                    <li>Guests must sign a waiver; guest fee is $5.</li>
                    <li>Do not leave items in locker/shower rooms; club not responsible for lost items.</li>
                </ul>

                <p style='margin-top:20px;'><strong>By signing below, I acknowledge that I have read, understood, and agree to be bound by this waiver, release of liability, membership agreement, and general rules.</strong></p>
            </div>

            <div class='signature-box'>
                <p><strong>Signature:</strong></p>
                <div class='signature'>" . htmlspecialchars($signature) . "</div>
                <p style='margin-top:20px;'><strong>Date:</strong> " . htmlspecialchars($dateSigned) . "</p>
            </div>

            <div class='footer'>
                <p><strong>Andalusia Health & Fitness</strong></p>
                <p>205 Church St, Andalusia, AL 36420 | Phone: (334) 582-2000</p>
                <p>Open 24/7 with key fob access</p>
            </div>
        </body>
        </html>
        ";

        // Generate PDF using Dompdf
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();
            $base64PDF = base64_encode($pdfContent);

            $attachments[] = [
                'name' => "Waiver_{$num}_" . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . ".pdf",
                'base64' => $base64PDF
            ];

        } catch (Throwable $e) {
            error_log("PDF generation error for waiver #{$num}: " . $e->getMessage());
            // Continue with other waivers even if one fails
        }
    }

    return $attachments;
}
