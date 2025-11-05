<?php
/**
 * Membership Signup Payment Bridge
 * Reads data from sessionStorage and creates Authorize.Net hosted payment page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processing Payment — Andalusia Health & Fitness</title>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    body {
      background:#000; color:#fff; text-align:center; font-family:"Helvetica Neue",Arial,sans-serif;
      padding:80px 20px 40px;
    }
    .box {
      max-width:480px; margin:auto; background:#111; border-radius:16px;
      padding:40px; box-shadow:0 0 25px rgba(216,27,96,0.3);
      border:1px solid rgba(216,27,96,0.2);
    }
    h1 { color:#d81b60; margin-bottom:12px; }
    .spinner {
      border: 4px solid rgba(255, 255, 255, 0.1);
      border-top: 4px solid #d81b60;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .error {
      background: #220b12;
      border: 1px solid #6f1634;
      padding: 15px;
      border-radius: 10px;
      margin-top: 20px;
      display: none;
    }
  </style>
</head>

<body class="theme-andalusia">

<!-- Topbar -->
<div class="topbar">
  <div class="shell">
    <div class="brand-pill">
      <a href="index.html"><img src="AHFlogo.png" alt="Andalusia Health & Fitness"></a>
    </div>
    <nav class="mainnav">
      <a href="index.html">Home</a>
      <a href="pricing.html">Pricing</a>
      <a href="https://maps.google.com/?q=205+Church+St,+Andalusia,+AL+36420" target="_blank" rel="noopener">Location</a>
    </nav>
  </div>
</div>

<!-- CTA -->
<div class="ctabar">
  <div class="shell">
    <a href="/quickpay/" class="pill-cta">Quick Pay</a>
    <a href="tel:+13345822000" class="pill-cta">Call • (334) 582-2000</a>
  </div>
</div>

  <div class="box">
    <h1>Processing Payment</h1>
    <p>Please wait while we prepare your secure payment page...</p>
    <div class="spinner"></div>
    <div id="errorBox" class="error"></div>
  </div>

  <script>
  (function() {
    // Read signup data from sessionStorage
    const signupData = JSON.parse(sessionStorage.getItem('ahfSignup') || '{}');

    if (!signupData.today_total || !signupData.first_name) {
      showError('No membership data found. Please start from the membership form.');
      setTimeout(() => {
        window.location.href = 'membership.html';
      }, 3000);
      return;
    }

    // Create invoice number (timestamp-based)
    const invoice = 'MS' + Date.now();

    // Prepare data for backend
    const paymentData = {
      amount: parseFloat(signupData.today_total).toFixed(2),
      invoice: invoice,
      customer: {
        firstName: signupData.first_name,
        lastName: signupData.last_name,
        email: signupData.email,
        zip: signupData.zip || ''
      },
      signupData: signupData // Pass entire signup data for return URL
    };

    // Store invoice in sessionStorage for later retrieval
    signupData.invoice = invoice;
    sessionStorage.setItem('ahfSignup', JSON.stringify(signupData));

    // Call backend to get Authorize.Net token
    fetch('api/payments/authorize-membership.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(paymentData)
    })
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        showError('Error: ' + data.error);
        return;
      }

      if (!data.token) {
        showError('Failed to create payment page. Please try again or contact support.');
        return;
      }

      // Redirect to Authorize.Net hosted payment page
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = data.paymentUrl || 'https://test.authorize.net/payment/payment';

      const tokenInput = document.createElement('input');
      tokenInput.type = 'hidden';
      tokenInput.name = 'token';
      tokenInput.value = data.token;
      form.appendChild(tokenInput);

      document.body.appendChild(form);
      form.submit();
    })
    .catch(err => {
      console.error(err);
      showError('Network error. Please check your connection and try again.');
    });

    function showError(message) {
      const errorBox = document.getElementById('errorBox');
      errorBox.textContent = message;
      errorBox.style.display = 'block';
      document.querySelector('.spinner').style.display = 'none';
    }
  })();
  </script>

<footer class="sitefoot">
  <div class="shell">
    <div class="footer-content">
      <span>© <script>document.write(new Date().getFullYear())</script> Andalusia Health & Fitness — Open 24/7.</span>
      <nav class="footer-links" aria-label="Footer">
        <a href="/Legal/privacy.html">Privacy Policy</a> |
        <a href="/Legal/terms.html">Terms & Conditions</a>
      </nav>
    </div>
  </div>
</footer>

</body>
</html>
