<?php
// Authorize.Net Sandbox Config
define('AUTH_ENV', 'sandbox');
define('AUTH_API_URL', 'https://apitest.authorize.net/xml/v1/request.api');

define('AUTH_LOGIN_ID',        '9rwWD86pE79');      // your sandbox login
define('AUTH_TRANSACTION_KEY', '6TR6Dr35G637teuH'); // your sandbox trans key

// NEW: paste the 128-char hex Signature Key from the ANet dashboard
define('AUTH_SIGNATURE_KEY_HEX', '21C260460E71D8FFC7437BA38D1938A0D8C531810250F8FC36CB06197C93776609F7A6AB937FD53DE0C0E43409B4BF6AC89F7AC1FBA77BDFA4DE9ECC535F3C7C');

// NEW: used for return URLs (optional, but handy)
define('SITE_BASE_URL', 'https://andalusiahealthandfitness.com');


