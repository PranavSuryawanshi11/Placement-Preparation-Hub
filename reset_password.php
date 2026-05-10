<?php
session_start();
require_once 'config.php';

$error   = '';
$success = '';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed     = password_hash($new_password, PASSWORD_DEFAULT);
        $email_safe = mysqli_real_escape_string($conn, $email);

        $update = mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE email = '$email_safe'");

        if ($update && mysqli_affected_rows($conn) > 0) {
            mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$email_safe'");
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);
            $success = "Password reset successfully! Redirecting to login...";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - PrepHub</title>
<link rel="stylesheet" href="style.css">
<style>
  /* Strength bar */
  .strength-bar-wrap {
    width: 100%; background: #eee; border-radius: 6px;
    height: 6px; margin: 6px 0 4px;
  }
  .strength-bar {
    height: 6px; border-radius: 6px;
    width: 0%; transition: width 0.3s, background 0.3s;
  }
  .strength-label {
    font-size: 12px; color: #888;
    margin-bottom: 14px; display: block;
  }

  /* Password field wrapper */
  .pw-wrap {
    position: relative;
    width: 100%;
    margin-bottom: 4px;
  }
  .pw-wrap input[type="password"],
  .pw-wrap input[type="text"] {
    width: 100% !important;
    padding-right: 48px !important;
    box-sizing: border-box !important;
  }
  .pw-wrap .eye-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 20px;
    color: #999;
    padding: 0;
    line-height: 1;
    z-index: 10;
  }
  .pw-wrap .eye-btn:hover { color: #4f46e5; }

  /* Label */
  .pw-label {
    display: block;
    font-size: 13px;
    color: #555;
    font-weight: 600;
    margin-bottom: 6px;
    margin-top: 14px;
  }

  /* Popup */
  .popup-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.5); z-index: 999;
    justify-content: center; align-items: center;
  }
  .popup-overlay.show { display: flex; }
  .popup-box {
    background: #fff; border-radius: 16px; padding: 32px 40px;
    text-align: center; box-shadow: 0 12px 48px rgba(0,0,0,0.2);
    max-width: 340px; width: 90%; animation: popIn 0.3s ease;
  }
  @keyframes popIn {
    from { transform: scale(0.8); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
  }
  .popup-box .popup-icon { font-size: 52px; margin-bottom: 12px; }
  .popup-box h3  { margin: 0 0 8px; font-size: 20px; }
  .popup-box p   { margin: 0 0 22px; color: #555; font-size: 14px; line-height: 1.5; }
  .popup-box button {
    padding: 11px 30px; border: none; border-radius: 8px;
    cursor: pointer; font-size: 14px; font-weight: bold;
    transition: opacity 0.2s;
  }
  .popup-box button:hover { opacity: 0.85; }
  .popup-error .popup-icon { color: #e74c3c; }
  .popup-error h3          { color: #e74c3c; }
  .popup-error button      { background: #e74c3c; color: #fff; }
  .popup-success .popup-icon { color: #2ecc71; }
  .popup-success h3          { color: #2ecc71; }
  .popup-success button      { background: #4f46e5; color: #fff; }
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
  <h2>Reset Password</h2>
  <p style="text-align:center;color:#666;font-size:14px;margin-bottom:20px;">
    Enter your new password below.<br>
    Make sure it's at least <strong>6 characters</strong>.
  </p>

  <form action="reset_password.php" method="POST" autocomplete="off">

    <!-- New Password -->
    <span class="pw-label">New Password</span>
    <div class="pw-wrap">
      <input type="password"
             name="new_password"
             id="new_password"
             placeholder="Enter new password"
             required>
      <button type="button" class="eye-btn" id="eye1" onclick="toggleEye('new_password','eye1')">🙈</button>
    </div>

    <!-- Strength Bar -->
    <div class="strength-bar-wrap">
      <div class="strength-bar" id="strengthBar"></div>
    </div>
    <span class="strength-label" id="strengthLabel">Password strength</span>

    <!-- Confirm Password -->
    <span class="pw-label">Confirm Password</span>
    <div class="pw-wrap" style="margin-bottom:20px;">
      <input type="password"
             name="confirm_password"
             id="confirm_password"
             placeholder="Re-enter new password"
             required>
      <button type="button" class="eye-btn" id="eye2" onclick="toggleEye('confirm_password','eye2')">🙈</button>
    </div>

    <button type="submit">Reset Password</button>
    <p style="text-align:center;margin-top:14px;">
      Remember your password? <a href="login.php">Login</a>
    </p>

  </form>
</div>

<!-- ERROR POPUP -->
<?php if (!empty($error)): ?>
<div class="popup-overlay show" id="errorPopup">
  <div class="popup-box popup-error">
    <div class="popup-icon">❌</div>
    <h3>Oops!</h3>
    <p><?php echo htmlspecialchars($error); ?></p>
    <button onclick="document.getElementById('errorPopup').classList.remove('show')">Try Again</button>
  </div>
</div>
<?php endif; ?>

<!-- SUCCESS POPUP -->
<?php if (!empty($success)): ?>
<div class="popup-overlay show" id="successPopup">
  <div class="popup-box popup-success">
    <div class="popup-icon">✅</div>
    <h3>Password Reset!</h3>
    <p><?php echo htmlspecialchars($success); ?></p>
    <button onclick="window.location.href='login.php'">Go to Login →</button>
  </div>
</div>
<?php endif; ?>

<script>
// ── Eye Toggle ──
// Default: hidden (🙈), click to show (👁️), click again to hide (🙈)
function toggleEye(fieldId, btnId) {
    const input = document.getElementById(fieldId);
    const btn   = document.getElementById(btnId);

    if (input.type === 'password') {
        input.type      = 'text';   // show password
        btn.textContent = '👁️';    // eye open
    } else {
        input.type      = 'password'; // hide password
        btn.textContent = '🙈';       // eye closed
    }
}

// ── Password Strength ──
document.getElementById('new_password').addEventListener('input', function () {
    const val = this.value;
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');

    let strength = 0;
    if (val.length >= 6)                         strength++;
    if (val.length >= 10)                        strength++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) strength++;
    if (/[0-9]/.test(val))                       strength++;
    if (/[^A-Za-z0-9]/.test(val))               strength++;

    const levels = [
        { width: '0%',   color: '#eee',    text: 'Password strength' },
        { width: '25%',  color: '#e74c3c', text: '🔴 Weak'           },
        { width: '50%',  color: '#e67e22', text: '🟠 Fair'           },
        { width: '75%',  color: '#f1c40f', text: '🟡 Good'           },
        { width: '90%',  color: '#2ecc71', text: '🟢 Strong'         },
        { width: '100%', color: '#27ae60', text: '💪 Very Strong'    },
    ];

    const level          = levels[Math.min(strength, 5)];
    bar.style.width      = level.width;
    bar.style.background = level.color;
    lbl.textContent      = level.text;
});

// ── Auto redirect after success ──
<?php if (!empty($success)): ?>
setTimeout(function () {
    window.location.href = 'login.php';
}, 3000);
<?php endif; ?>
</script>

</body>
</html>