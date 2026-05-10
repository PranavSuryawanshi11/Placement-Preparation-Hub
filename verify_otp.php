<?php
session_start();
require_once 'config.php';

// Redirect if no reset session
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp   = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];

    if (empty($otp)) {
        $error = "Please enter the OTP.";
    } else {
        $now    = date('Y-m-d H:i:s');
        $result = mysqli_query($conn, "SELECT * FROM password_resets WHERE email = '$email' AND otp = '$otp' AND expires_at > '$now' LIMIT 1");

        if (mysqli_num_rows($result) === 1) {
            // OTP valid — allow reset
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid or expired OTP. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Verify OTP - PrepHub</title>
<link rel="stylesheet" href="style.css">
<style>
  .popup-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; justify-content:center; align-items:center; }
  .popup-overlay.show { display:flex; }
  .popup-box { background:#fff; border-radius:12px; padding:30px 40px; text-align:center; box-shadow:0 10px 40px rgba(0,0,0,0.2); max-width:320px; width:90%; animation:popIn 0.3s ease; }
  @keyframes popIn { from{transform:scale(0.8);opacity:0} to{transform:scale(1);opacity:1} }
  .popup-box .popup-icon { font-size:48px; margin-bottom:12px; }
  .popup-box h3 { margin:0 0 8px; font-size:20px; }
  .popup-box p  { margin:0 0 20px; color:#555; font-size:14px; }
  .popup-box button { padding:10px 28px; border:none; border-radius:8px; cursor:pointer; font-size:14px; font-weight:bold; }
  .popup-error .popup-icon { color:#e74c3c; }
  .popup-error h3          { color:#e74c3c; }
  .popup-error button      { background:#e74c3c; color:#fff; }

  .otp-hint { text-align:center; font-size:13px; color:#888; margin-bottom:16px; }
</style>
</head>
<body class="login-body">

<header>
  <div class="logo">PrepHub</div>
  <nav>
    <a href="index.html">Home</a>
    <a href="#">Subjects</a>
    <a href="login.php">Login</a>
  </nav>
</header>

<div class="form-container">
  <h2>Enter OTP</h2>
  <p class="otp-hint">We sent a 6-digit OTP to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>

  <form action="verify_otp.php" method="POST">
    <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required style="letter-spacing:8px;font-size:20px;text-align:center;">
    <button type="submit">Verify OTP</button>
    <p><a href="forgot_password.php">← Resend OTP</a></p>
  </form>
</div>

<!-- ERROR POPUP -->
<?php if (!empty($error)): ?>
<div class="popup-overlay show" id="errorPopup">
  <div class="popup-box popup-error">
    <div class="popup-icon">✗</div>
    <h3>Invalid OTP</h3>
    <p><?php echo htmlspecialchars($error); ?></p>
    <button onclick="document.getElementById('errorPopup').classList.remove('show')">Try Again</button>
  </div>
</div>
<?php endif; ?>

</body>
</html>