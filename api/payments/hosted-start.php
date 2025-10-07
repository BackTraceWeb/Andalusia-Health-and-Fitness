<?php
require __DIR__ . '/../_bootstrap.php';

// Connect to the database
$pdo = pdo();

// Get parameters
$memberId  = $_POST['memberId'] ?? '';
$invoiceId = $_POST['invoiceId'] ?? '';

if (!$memberId || !$invoiceId) {
    die("Missing memberId or invoiceId");
}

// Look up invoice + member
$sql = "SELECT m.first_name, m.last_name, m.email, m.zip, d.amount_cents
        FROM dues d
        JOIN members m ON m.id = d.member_id
        WHERE d.id = :invoiceId AND m.id = :memberId";
$stmt = $pdo->prepare($sql);
$stmt->execute(['invoiceId' => $invoiceId, 'memberId' => $memberId]);
$data = $stmt->fetch();

if (!$data) {
    die("Invoice or member not found");
}

$amount = number_format($data['amount_cents'] / 100, 2, '.', '');

// Pull credentials from Apache environment
$epnAccount = getenv('EPN_ACCOUNT');
$epnKey     = getenv('EPN_RESTRICT_KEY');

// Build fields for the hosted EPN form
$fields = [
    'ePNAccount'   => $epnAccount,
    'RestrictKey'  => $epnKey,
    'HTML'         => 'Y',
    'FormType'     => 'Standard',
    'Total'        => $amount,
    'CardName'     => "{$data['first_name']} {$data['last_name']}",
    'Email'        => $data['email'],
    'Zip'          => $data['zip'],
    'PaymentType'  => 'CC'
];

// Render HTML to auto-submit
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Redirecting...</title></head><body>';
echo '<p>Redirecting to Secure Payment...</p>';
echo '<form id="epnForm" method="POST" action="https://www.eprocessingnetwork.com/cgi-bin/dbe/transact.pl">';
foreach ($fields as $name => $value) {
    $safeValue = htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<input type='hidden' name='" . htmlspecialchars($name) . "' value='{$safeValue}'>";
}

echo '</form>';
echo '<script>document.getElementById("epnForm").submit();</script>';
echo '</body></html>';
