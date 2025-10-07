<?php
header('Content-Type: application/json');
require __DIR__ . '/_bootstrap.php';
$c = require __DIR__ . '/../config/payments.php';

function dbe_call(array $c, array $fields) {
  $ch = curl_init($c['dbe_endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query($fields),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>30,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2,
  ]);
  $r = curl_exec($ch); $e=curl_error($ch); curl_close($ch);
  if ($r === false) return ['ok'=>false,'error'=>$e];
  $lines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $r))));
  $rows=[]; foreach($lines as $ln){ $rows[] = str_getcsv($ln); }
  return ['ok'=>true,'rows'=>$rows,'raw'=>$r];
}

$base = ['ePNAccount'=>$c['epn_account'], 'RestrictKey'=>$c['restrict_key'], 'HTML'=>'No', 'Action'=>'Query', 'Limit'=>5];

$try = dbe_call($c, $base + ['Table'=>'Recur']);
if (!$try['ok'] || count($try['rows']) < 2) {
  $try = dbe_call($c, $base + ['Table'=>'Customers']);
}
echo json_encode($try);
