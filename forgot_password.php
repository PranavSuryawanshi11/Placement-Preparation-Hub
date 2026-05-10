<?php
session_start();
require_once 'config.php';

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim(mysqli_real_escape_string($conn, $_POST['login']));

    if (empty($login)) {
        $error = "Please enter your email or mobile number.";
    } else {
        $is_email  = filter_var($login, FILTER_VALIDATE_EMAIL);
        $is_mobile = preg_match('/^[0-9]{10}$/', $login);

        if (!$is_email && !$is_mobile) {
            $error = "Please enter a valid email or 10-digit mobile number.";
        } else {
            $where  = $is_email ? "email = '$login'" : "mobile = '$login'";
            $result = mysqli_query($conn, "SELECT id, email, mobile FROM users WHERE $where LIMIT 1");

            if (mysqli_num_rows($result) === 0) {
                $error = "No account found with this email or mobile number.";
            } else {
                $user        = mysqli_fetch_assoc($result);
                $user_email  = $user['email'];
                $user_mobile = $user['mobile'] ?? '';

                $otp     = rand(100000, 999999);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$user_email'");
                mysqli_query($conn, "INSERT INTO password_resets (email, otp, expires_at) 
                                     VALUES ('$user_email', '$otp', '$expires')");

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'pranavsuryawanshi4k@gmail.com';     // ✅ YOUR GMAIL
                    $mail->Password   = 'tmzx yxzl ytpn wtyp';     // ✅ YOUR APP PASSWORD
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('prephub763@gmail.com', 'PrepHub');
                    $mail->addAddress($user_email);
                    $mail->Subject = 'PrepHub - Password Reset OTP';
                    $mail->isHTML(true);

                    $masked_email = substr($user_email, 0, 3) 
                                  . str_repeat('*', max(1, strpos($user_email, '@') - 3)) 
                                  . strstr($user_email, '@');
                    $input_type   = $is_mobile 
                                  ? "mobile number <b>+91" . $login . "</b>" 
                                  : "email";

                    $mail->Body = "
                        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;
                                    padding:30px;border:1px solid #e0e0e0;border-radius:16px;
                                    background:#fafafa;'>
                            <div style='text-align:center;margin-bottom:20px;'>
                                <h2 style='color:#4f46e5;margin:0;'>🔐 PrepHub</h2>
                                <p style='color:#888;font-size:13px;margin:4px 0 0;'>Password Reset Request</p>
                            </div>
                            <hr style='border:none;border-top:1px solid #eee;margin:0 0 24px;'>
                            <p style='font-size:15px;color:#333;'>
                                We received a reset request for your $input_type.<br>
                                Your One-Time Password is:
                            </p>
                            <div style='text-align:center;margin:24px 0;'>
                                <span style='display:inline-block;font-size:40px;font-weight:bold;
                                             letter-spacing:14px;color:#4f46e5;background:#eef2ff;
                                             padding:16px 28px;border-radius:12px;'>$otp</span>
                            </div>
                            <p style='font-size:13px;color:#888;text-align:center;'>
                                ⏱ This OTP is valid for <strong>10 minutes</strong> only.<br>
                                Do <strong>not</strong> share this with anyone.
                            </p>
                            <hr style='border:none;border-top:1px solid #eee;margin:24px 0 16px;'>
                            <p style='font-size:11px;color:#bbb;text-align:center;'>
                                If you didn't request this, please ignore this email.<br>
                                &copy; PrepHub – All rights reserved.
                            </p>
                        </div>
                    ";
                    $mail->AltBody = "Your PrepHub OTP is: $otp. Valid for 10 minutes. Do not share it.";
                    $mail->send();

                    $_SESSION['reset_email'] = $user_email;

                    if ($is_mobile) {
                        $success = "OTP sent to the email linked with your mobile number.";
                    } else {
                        $success = "OTP sent successfully to <b>$masked_email</b>";
                    }

                } catch (Exception $e) {
                    $error = "Failed to send OTP. Error: " . $mail->ErrorInfo;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - PrepHub</title>
<link rel="stylesheet" href="style.css">
<style>
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
  <h2>Forgot Password</h2>
  <p style="text-align:center;color:#666;font-size:14px;margin-bottom:20px;">
    Enter your registered <strong>Email</strong> or <strong>10-digit Mobile Number</strong>.<br>
    We'll send a 6-digit OTP to your registered email.
  </p>

  <form action="forgot_password.php" method="POST">
    <input type="text"
           name="login"
           placeholder="Email or 10-digit Mobile Number"
           value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>"
           required>
    <button type="submit">Send OTP</button>
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
    <h3>OTP Sent!</h3>
    <p><?php echo $success; ?></p>
    <button onclick="window.location.href='verify_otp.php'">Enter OTP →</button>
  </div>
</div>
<?php endif; ?>

</body>
</html>