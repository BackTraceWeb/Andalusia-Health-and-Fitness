<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Processing Membership - Andalusia Health & Fitness</title>
  <link rel="stylesheet" href="../../styles.css"/>
  <style>
    body {
      background: #000;
      color: #fff;
      font-family: "Helvetica Neue", Arial, sans-serif;
      text-align: center;
      padding: 80px 20px 40px;
    }
    h1 {
      color: #d81b60;
    }
    .spinner {
      border: 4px solid rgba(255, 255, 255, 0.1);
      border-top: 4px solid #d81b60;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .card {
      background: #111;
      border-radius: 20px;
      padding: 40px;
      display: inline-block;
      box-shadow: 0 0 30px rgba(216, 27, 96, 0.3);
      border: 1px solid rgba(216, 27, 96, 0.2);
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
      <a href="../../index.html"><img src="../../AHFlogo.png" alt="Andalusia Health & Fitness"></a>
    </div>
    <nav class="mainnav">
      <a href="../../index.html">Home</a>
      <a href="../../pricing.html">Pricing</a>
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

  <div class="card">
    <h1>✅ Payment Successful!</h1>
    <p>Processing your membership...</p>
    <div class="spinner"></div>
    <div id="errorBox" class="error"></div>
  </div>

  <script>
  (function() {
    // Read signup data from sessionStorage
    const signupData = JSON.parse(sessionStorage.getItem('ahfSignup') || '{}');

    if (!signupData.first_name || !signupData.invoice) {
      showError('Membership data not found. Please contact support.');
      return;
    }

    // POST to backend to create member record and send email
    fetch('../process-membership.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(signupData)
    })
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        showError('Error: ' + data.error);
        return;
      }

      if (data.success) {
        // Clear sessionStorage
        sessionStorage.removeItem('ahfSignup');

        // Redirect to thank you page
        window.location.href = '/thank-you.html';
      } else {
        showError('An unexpected error occurred. Please contact support.');
      }
    })
    .catch(err => {
      console.error(err);
      showError('Network error. Your payment was successful, but we could not complete your registration. Please contact support with invoice: ' + signupData.invoice);
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
