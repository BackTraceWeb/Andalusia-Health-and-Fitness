<?php
// Authorize.Net Sandbox Config
define('AUTH_ENV', 'PROD');
define('AUTH_API_URL', 'https://api2.authorize.net/xml/v1/request.api');

define('AUTH_LOGIN_ID',        '75aKSj4J5');      // your sandbox login
define('AUTH_TRANSACTION_KEY', '27Pdsz96u2EsC693'); // your sandbox trans key

// NEW: paste the 128-char hex Signature Key from the ANet dashboard
define('AUTH_SIGNATURE_KEY_HEX', '1402B82DA340E43F9D13A8B85FF320919BCE81D56307534ED467BD00471C9C669201D0001F58F519D38631588862943BE06BBD9BC99F22EE38FF34B77E36EE87');

// NEW: used for return URLs (optional, but handy)
define('SITE_BASE_URL', 'https://andalusiahealthandfitness.com');


