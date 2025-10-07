<?php
declare(strict_types=1);
header('Content-Type: application/json');

use Aws\S3\S3Client;
use Dompdf\Dompdf;

require_once __DIR__ . '/../vendor/autoload.php';
$cfg = require __DIR__ . '/../config/.env.php';

function bailout(int $code, string $msg, array $extra = []): never {
  http_response_code($code);
  echo json_encode(['status'=>'error','error'=>$msg] + $extra);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bailout(405, 'method_not_allowed');

/* ===== Inputs from frontend ===== */
$memberName   = trim($_POST['member_name'] ?? '');
$memberEmail  = trim($_POST['member_email'] ?? '');
$memberPhone  = trim($_POST['member_phone'] ?? '');
$plan         = trim($_POST['plan'] ?? '');
$startDate    = trim($_POST['start_date'] ?? '');
$monthlyTotal = trim($_POST['monthly_total'] ?? '0');
$todayTotal   = trim($_POST['today_total'] ?? '0');
$waiverCount  = (int)($_POST['waiver_count'] ?? 0);
$signupJson   = $_POST['signup_json'] ?? '{}';
$waiverMeta   = $_POST['waiver_meta'] ?? '[]';   // JSON array of signers (order matches files)
$axtraxResult = $_POST['axtrax_payload'] ?? '{}';

/* ===== S3 init ===== */
try {
  $s3 = new S3Client(['version'=>'latest', 'region'=>$cfg['aws']['region']]);
} catch (Throwable $e) {
  bailout(500,'s3_init_failed',['message'=>$e->getMessage()]);
}

$bucket = $cfg['aws']['bucket'];
$slug   = preg_replace('/[^a-z0-9]+/i','_', $memberName ?: 'member');
$stamp  = date('Ymd-His');
$prefix = sprintf('env=prod/year=%s/month=%s/member=%s_%s/', date('Y'), date('m'), $slug, $stamp);
$s3Keys = [];

/* ===== helpers ===== */
function s3_put_file(Aws\S3\S3Client $s3, string $bucket, string $key, string $file, string $ctype, array &$s3Keys): void {
  $s3->putObject([
    'Bucket'=>$bucket, 'Key'=>$key, 'SourceFile'=>$file,
    'ContentType'=>$ctype, 'ACL'=>'private', 'ServerSideEncryption'=>'AES256'
  ]);
  $s3Keys[] = $key;
}
function s3_put_bytes(Aws\S3\S3Client $s3, string $bucket, string $key, string $bytes, string $ctype, array &$s3Keys): void {
  $tmp = tempnam(sys_get_temp_dir(), 'ahf_');
  file_put_contents($tmp, $bytes);
  s3_put_file($s3, $bucket, $key, $tmp, $ctype, $s3Keys);
  @unlink($tmp);
}
function save_uploads_to_s3(string $formKey, string $base, Aws\S3\S3Client $s3, string $bucket, string $prefix, array &$s3Keys): void {
  if (!isset($_FILES[$formKey])) return;
  $files = $_FILES[$formKey];
  $n = is_array($files['name']) ? count($files['name']) : 1;
  for ($i=0; $i<$n; $i++){
    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    if ($err !== UPLOAD_ERR_OK) continue;
    $ext  = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
    $ctype= mime_content_type($tmp) ?: 'application/octet-stream';
    $key  = $prefix . sprintf('%s_%d.%s', $base, $i+1, $ext);
    s3_put_file($s3, $bucket, $key, $tmp, $ctype, $s3Keys);
  }
}

/* ===== Upload photo (as-is) ===== */
try {
  save_uploads_to_s3('photo', 'photo', $s3, $bucket, $prefix, $s3Keys);
} catch (Throwable $e) {
  bailout(500, 's3_upload_failed', ['message'=>$e->getMessage()]);
}

/* ===== Waiver PDF generation from your waiver.html ===== */
try {
  $templatePath = realpath(__DIR__ . '/../waiver.html');
  $stylesPath   = realpath(__DIR__ . '/../styles.css');
  $templateHtml = $templatePath && is_readable($templatePath) ? file_get_contents($templatePath) : '';
  $inlineCss    = $stylesPath && is_readable($stylesPath) ? file_get_contents($stylesPath) : '';

  // Normalize template head to inject CSS if needed
  $wrapTemplate = function(string $html, string $css): string {
    // If the template is a full HTML page, inject CSS; otherwise wrap it.
    if (stripos($html, '<html') !== false) {
      // Inject CSS into head (or create one)
      if (stripos($html, '</head>') !== false) {
        return preg_replace('~</head>~i', "<style>\n{$css}\n</style>\n</head>", $html, 1);
      } else {
        return "<head><meta charset=\"utf-8\"><style>{$css}</style></head>".$html;
      }
    } else {
      return "<!doctype html><html><head><meta charset=\"utf-8\"><style>{$css}</style></head><body>{$html}</body></html>";
    }
  };

  // decode signer metadata (array of objects, order matches files)
  $signers = json_decode($waiverMeta, true);
  if (!is_array($signers)) $signers = [];

  if (isset($_FILES['waiver_png'])) {
    $files = $_FILES['waiver_png'];
    $n = is_array($files['name']) ? count($files['name']) : 1;

    for ($i=0; $i<$n; $i++) {
      $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
      $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];
      if ($err !== UPLOAD_ERR_OK) continue;

      $sigB64 = 'data:image/png;base64,'.base64_encode(file_get_contents($tmp));
      $signer = $signers[$i] ?? [];

      // Fields we try to fill
      $vars = [
        '{{MEMBER_NAME}}'   => $memberName,
        '{{SIGNER_NAME}}'   => $signer['name']     ?? $memberName,
        '{{EMAIL}}'         => $signer['email']    ?? $memberEmail,
        '{{PHONE}}'         => $signer['phone']    ?? $memberPhone,
        '{{DOB}}'           => $signer['dob']      ?? '',
        '{{ADDRESS}}'       => $signer['address']  ?? '',
        '{{CITY}}'          => $signer['city']     ?? '',
        '{{STATE}}'         => $signer['state']    ?? '',
        '{{ZIP}}'           => $signer['zip']      ?? '',
        '{{DATE}}'          => $signer['signedAt'] ?? date('Y-m-d H:i:s'),
        '{{SIGNATURE_IMG}}' => $sigB64
      ];

      // Build the waiver HTML:
      $htmlToRender = '';
      if ($templateHtml !== '') {
        $filled = $templateHtml;

        // Replace placeholders if present
        foreach ($vars as $k => $v) {
          $filled = str_replace($k, htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $filled);
        }

        // If the template includes an <img ... id="signature" or {{SIGNATURE_IMG}}, try to inject the image src.
        // 1) {{SIGNATURE_IMG}} inside an <img> tag (simple case)
        if (strpos($templateHtml, '{{SIGNATURE_IMG}}') !== false) {
          $filled = str_replace('{{SIGNATURE_IMG}}', $sigB64, $filled);
        } else {
          // 2) <img id="signature"> or data-sig slot — we try a quick replace
          $filled = preg_replace(
            '~(<img\b[^>]*\bid=(["\'])signature\2[^>]*\bsrc=(["\'])(.*?)\3[^>]*>)~i',
            '<img id="signature" src="'.$sigB64.'" />',
            $filled
          );
        }

        // Wrap & inject CSS
        $htmlToRender = $wrapTemplate($filled, $inlineCss);
      } else {
        // Fallback (no template found): cover page with info + signature
        $htmlToRender = $wrapTemplate(
          '<h1>Signed Waiver</h1>
           <div style="margin:6px 0 12px">
             <strong>Member:</strong> '.htmlspecialchars($memberName).'<br>
             <strong>Signer:</strong> '.htmlspecialchars($vars['{{SIGNER_NAME}}']).'<br>
             <strong>Signed at:</strong> '.htmlspecialchars($vars['{{DATE}}']).'
           </div>
           <div style="border:1px solid #ccc;border-radius:8px;padding:10px">
             <img src="'.$sigB64.'" style="max-width:100%;height:auto">
           </div>',
          $inlineCss
        );
      }

      // Render -> PDF
      $dom = new Dompdf(['isRemoteEnabled'=>true]);
      $dom->loadHtml($htmlToRender, 'UTF-8');
      $dom->setPaper('Letter', 'portrait');
      $dom->render();
      $pdfBytes = $dom->output();

      $key = $prefix . 'waiver_' . ($i+1) . '.pdf';
      s3_put_bytes($s3, $bucket, $key, $pdfBytes, 'application/pdf', $s3Keys);
    }
  }
} catch (Throwable $e) {
  bailout(500, 'waiver_pdf_failed', ['message'=>$e->getMessage()]);
}

/* ===== membership.pdf ===== */
$pdfBytes = '';
try {
  $summaryHtml = '<html><head><meta charset="utf-8"><style>
    body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111}
    h1{font-size:20px;margin:0 0 8px} h2{font-size:16px;margin:16px 0 6px}
    .row{display:flex;justify-content:space-between;margin:4px 0}
    .muted{color:#666}.box{border:1px solid #ddd;border-radius:8px;padding:10px;margin:10px 0}
  </style></head><body>
    <h1>Membership Summary</h1>
    <div class="box">
      <div class="row"><strong>Member:</strong><span>'.htmlspecialchars($memberName).'</span></div>
      <div class="row"><strong>Email:</strong><span>'.htmlspecialchars($memberEmail).'</span></div>
      <div class="row"><strong>Phone:</strong><span>'.htmlspecialchars($memberPhone).'</span></div>
      <div class="row"><strong>Plan:</strong><span>'.htmlspecialchars($plan).'</span></div>
      <div class="row"><strong>Start Date:</strong><span>'.htmlspecialchars($startDate).'</span></div>
      <div class="row"><strong>Today\'s Total:</strong><span>$'.htmlspecialchars($todayTotal).'</span></div>
      <div class="row"><strong>Monthly Total:</strong><span>$'.htmlspecialchars($monthlyTotal).'</span></div>
      <div class="row"><strong>Waiver Count:</strong><span>'.(int)$waiverCount.'</span></div>
    </div>
    <h2>Waiver Signers</h2>
    <div class="box"><pre class="muted" style="white-space:pre-wrap;">'.htmlspecialchars($waiverMeta).'</pre></div>
    <h2>Signup Payload</h2>
    <div class="box"><pre class="muted" style="white-space:pre-wrap;">'.htmlspecialchars($signupJson).'</pre></div>
    <h2>AxTrax Member Record</h2>
    <div class="box"><pre class="muted" style="white-space:pre-wrap;">'.htmlspecialchars($axtraxResult).'</pre></div>
  </body></html>';

  $dompdf = new Dompdf(['isRemoteEnabled'=>true]);
  $dompdf->loadHtml($summaryHtml, 'UTF-8');
  $dompdf->setPaper('Letter', 'portrait');
  $dompdf->render();
  $pdfBytes = $dompdf->output();

  s3_put_bytes($s3, $bucket, $prefix.'membership.pdf', $pdfBytes, 'application/pdf', $s3Keys);
} catch (Throwable $e) {
  // continue without membership.pdf if needed
}

/* ===== Presigned URLs ===== */
$urls = [];
try {
  foreach ($s3Keys as $key) {
    $cmd = $s3->getCommand('GetObject', ['Bucket'=>$bucket, 'Key'=>$key]);
    $req = $s3->createPresignedRequest($cmd, $cfg['aws']['presign_expires']);
    $urls[] = (string)$req->getUri();
  }
} catch (Throwable $e) {}

/* ===== Email build ===== */
$subject = 'New Membership: '.($memberName ?: 'New Member').' — '.$plan;
$linksHtml = '';
foreach ($urls as $u) {
  $label = basename(parse_url($u, PHP_URL_PATH));
  $linksHtml .= '<li style="margin:6px 0;"><a href="'.htmlspecialchars($u).'" style="color:#d81b60;text-decoration:none;">'.htmlspecialchars($label).'</a></li>';
}
$linksTxt = implode("\n", array_map(fn($u)=>"- ".$u, $urls));
$htmlEmail = <<<HTML
<!doctype html><html><head><meta charset="utf-8"><meta name="color-scheme" content="light dark">
<style>
  .wrap{max-width:640px;margin:0 auto;padding:16px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}
  .muted{color:#64748b}.h1{font-size:20px;font-weight:800;margin:0 0 10px;color:#0b0b0b}
  .brand{color:#d81b60;font-weight:800}@media (prefers-color-scheme: dark){
    body{background:#0b0b0b}.card{background:#111418;border-color:#263041}.h1{color:#fff}.muted{color:#9aa8bb}
  }
</style></head><body style="margin:0;padding:0;background:#f5f6f8;">
<div class="wrap">
  <div style="text-align:center;margin:10px 0 16px;">
    <img src="cid:ahf_logo" alt="Andalusia Health & Fitness" style="max-width:260px;height:auto">
  </div>
  <div class="card">
    <div class="h1">New <span class="brand">Membership</span> Completed</div>
    <div class="muted" style="margin-bottom:12px">This email includes the member’s details and links to signed PDFs &amp; photo.</div>

    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Member</strong><span>{$memberName}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Email</strong><span>{$memberEmail}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Phone</strong><span>{$memberPhone}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Plan</strong><span>{$plan}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Start Date</strong><span>{$startDate}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Today’s Total</strong><span>\${$todayTotal}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Monthly Total</strong><span>\${$monthlyTotal}</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px;margin:6px 0"><strong>Waiver Count</strong><span>{$waiverCount}</span></div>

    <div style="margin-top:14px;border-top:1px solid #e5e7eb;padding-top:12px;">
      <strong>Files (expire in 7 days):</strong>
      <ul style="padding-left:18px;margin:10px 0 0">{$linksHtml}</ul>
    </div>

    <div class="muted" style="margin-top:14px;font-size:12px">
      Note: The member record has been created in AxTrax. Staff will add a fob on first visit.
    </div>
  </div>
</div>
</body></html>
HTML;

$textEmail =
"New Membership Completed\n\n".
"Member: {$memberName}\nEmail: {$memberEmail}\nPhone: {$memberPhone}\n".
"Plan: {$plan}\nStart Date: {$startDate}\n".
"Today's Total: \${$todayTotal}\nMonthly Total: \${$monthlyTotal}\n".
"Waiver Count: {$waiverCount}\n\n".
"Files (expire in 7 days):\n{$linksTxt}\n\n".
"Note: Member record created in AxTrax; staff will add a fob on first visit.\n";

/* ===== Microsoft Graph mail ===== */
function graph_send_mail(array $cfg,string $subject,string $html,string $text,?string $pdfBytes,string $memberEmail,string $memberName):void{
  $tenant=$cfg['graph']['tenant_id']; $clientId=$cfg['graph']['client_id']; $secret=$cfg['graph']['client_secret'];
  $sender=$cfg['graph']['sender_upn']; $to=$cfg['graph']['to']; $cc=$cfg['graph']['cc_member']&&filter_var($memberEmail,FILTER_VALIDATE_EMAIL);

  $ch=curl_init("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token");
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query([
    'client_id'=>$clientId,'client_secret'=>$secret,'grant_type'=>'client_credentials','scope'=>'https://graph.microsoft.com/.default'
  ]),CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
  $tok=json_decode(curl_exec($ch),true); $err=curl_error($ch); curl_close($ch);
  if(empty($tok['access_token'])) throw new RuntimeException('OAuth fail: '.$err);
  $accessToken=$tok['access_token'];

  $attachments=[];
  if(!empty($cfg['graph']['logo_path'])&&is_readable($cfg['graph']['logo_path'])){
    $attachments[]=[
      '@odata.type'=>'#microsoft.graph.fileAttachment',
      'name'=>'ahf-logo.png','contentType'=>'image/png','isInline'=>true,'contentId'=>'ahf_logo',
      'contentBytes'=>base64_encode(file_get_contents($cfg['graph']['logo_path']))
    ];
  }
  if(!empty($pdfBytes)){
    $attachments[]=[
      '@odata.type'=>'#microsoft.graph.fileAttachment',
      'name'=>'membership.pdf','contentType'=>'application/pdf','isInline'=>false,
      'contentBytes'=>base64_encode($pdfBytes)
    ];
  }

  $msg=['message'=>[
    'subject'=>$subject,
    'body'=>['contentType'=>'HTML','content'=>$html],
    'toRecipients'=>[['emailAddress'=>['address'=>$to]]],
    'ccRecipients'=>$cc?[['emailAddress'=>['address'=>$memberEmail,'name'=>$memberName]]]:[],
    'attachments'=>$attachments
  ], 'saveToSentItems'=>true];

  $ch=curl_init("https://graph.microsoft.com/v1.0/users/".rawurlencode($sender)."/sendMail");
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>["Authorization: Bearer $accessToken","Content-Type: application/json"],
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($msg),
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30
  ]);
  $resp=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  if($http>=300) throw new RuntimeException("Graph sendMail failed ($http): $resp $err");
}

try {
  graph_send_mail($cfg,$subject,$htmlEmail,$textEmail,$pdfBytes?:null,$memberEmail,$memberName);
  echo json_encode(['status'=>'ok','prefix'=>$prefix,'objects'=>$s3Keys]);
} catch (Throwable $e) {
  bailout(500,'graph_failed',['message'=>$e->getMessage(),'prefix'=>$prefix,'objects'=>$s3Keys]);
}
