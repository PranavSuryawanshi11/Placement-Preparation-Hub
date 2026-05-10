<?php
session_start();

// ✅ GUARD: If already logged in, always redirect — back button won't work either
if (!empty($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");

// ✅ NO CACHE: Prevents browser from serving cached login page on back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $is_email = filter_var($login, FILTER_VALIDATE_EMAIL);

        if ($is_email) {
            $stmt = $conn->prepare("SELECT id, name, email, mobile, password FROM users WHERE email = ? LIMIT 1");
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, mobile, password FROM users WHERE mobile = ? LIMIT 1");
        }

        if ($stmt) {
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']        = $user['id'];
                    $_SESSION['user_name']      = $user['name'];
                    $_SESSION['user_email']     = $user['email'];
                    $_SESSION['user_mobile']    = $user['mobile'];
                    $_SESSION['just_logged_in'] = true;

                    // ✅ PRG Pattern: Redirect prevents form resubmission on refresh
                    header("Location: index.html");
                    exit();
                } else {
                    $error = "Invalid email/mobile or password.";
                }
            } else {
                $error = "Invalid email/mobile or password.";
            }
            $stmt->close();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PrepHub</title>
    <!-- ✅ Meta no-cache: extra layer to stop browser caching this page -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="style.css">
    <style>
        .popup-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;justify-content:center;align-items:center;}
        .popup-overlay.show{display:flex;}
        .popup-box{background:#fff;border-radius:12px;padding:30px 40px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.2);max-width:320px;width:90%;animation:popIn 0.3s ease;}
        @keyframes popIn{from{transform:scale(0.8);opacity:0}to{transform:scale(1);opacity:1}}
        .popup-box .popup-icon{font-size:48px;margin-bottom:12px;}
        .popup-box h3{margin:0 0 8px;font-size:20px;}
        .popup-box p{margin:0 0 20px;color:#555;font-size:14px;}
        .popup-box button{padding:10px 28px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:bold;}
        .popup-error .popup-icon{color:#e74c3c;}.popup-error h3{color:#e74c3c;}.popup-error button{background:#e74c3c;color:#fff;}
        .forgot-link{text-align:right;margin-top:-8px;margin-bottom:12px;}
        .forgot-link a{font-size:13px;color:#4f46e5;text-decoration:none;}
        .forgot-link a:hover{text-decoration:underline;}
    </style>
</head>
<body class="login-body">

<header>
  <div class="logo">PrepHub</div>
  <nav>
    <a href="index.html">Home</a>
    <a href="#">Subjects</a>
  </nav>
</header>

<div class="form-container">
  <h2>Login to PrepHub</h2>
  <form action="login.php" method="POST" autocomplete="on">
    <input type="text"     name="login"    placeholder="Email or Mobile Number" required autocomplete="username">
    <input type="password" name="password" placeholder="Password"               required autocomplete="current-password">
    <div class="forgot-link">
      <a href="forgot_password.php">Forgot Password?</a>
    </div>
    <button type="submit">Login</button>
    <p>Don't have an account? <a href="register.php">Register</a></p>
  </form>
</div>

<?php if (!empty($error)): ?>
<div class="popup-overlay show" id="errorPopup">
  <div class="popup-box popup-error">
    <div class="popup-icon">✗</div>
    <h3>Login Failed</h3>
    <p><?php echo htmlspecialchars($error); ?></p>
    <button onclick="document.getElementById('errorPopup').classList.remove('show')">Try Again</button>
  </div>
</div>
<?php endif; ?>

</body>
</html>