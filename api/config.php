<?php
// Authorize.Net Sandbox Config
define('AUTH_ENV', 'sandbox');
define('AUTH_API_URL', 'https://apitest.authorize.net/xml/v1/request.api');

define('AUTH_LOGIN_ID',        '9rwWD86pE79');      // your sandbox login
define('AUTH_TRANSACTION_KEY', '6TR6Dr35G637teuH'); // your sandbox trans key

// NEW: paste the 128-char hex Signature Key from the ANet dashboard
define('AUTH_SIGNATURE_KEY_HEX', 'DDAFD7B1619098F45659EBF7E065360F3A4E8AD6C45D98111EB7C1A66F4B7F21CAA68447211DC72F4979A73B38E3D5520DD975A534D5CF9E6AE666F449796A2D');

// NEW: used for return URLs (optional, but handy)
define('SITE_BASE_URL', 'https://andalusiahealthandfitness.com');


