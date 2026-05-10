<?php
session_start();
require_once 'config.php';

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

define('ABSTRACT_API_KEY', '58c72b06d34b4e6d8570caa1556141d1');

$error   = '';
$success = '';

/* ══════════════════════════════════════════════════════════
   DNS-only check (last-resort fallback)
══════════════════════════════════════════════════════════ */
function dnsOnlyCheck(string $domain): bool {
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

/* ══════════════════════════════════════════════════════════
   Full email validation — always calls Abstract API
   Falls back to DNS ONLY if cURL fails completely (no internet)
══════════════════════════════════════════════════════════ */
function validateEmailFull(string $email): array {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'reason' => 'Invalid email format.'];
    }

    $domain = strtolower(explode('@', $email)[1]);

    $url = 'https://emailvalidation.abstractapi.com/v1/?api_key=' . ABSTRACT_API_KEY
         . '&email=' . urlencode($email);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'PrepHub/1.0',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    /* cURL failed completely — DNS fallback */
    if ($response === false || !empty($curlError) || $httpCode === 0) {
        $ok = dnsOnlyCheck($domain);
        return ['valid' => $ok, 'reason' => $ok ? '' : "Email domain <b>$domain</b> does not exist."];
    }

    /* API key invalid */
    if ($httpCode === 401) {
        return ['valid' => false, 'reason' => 'Email verification service error. Please contact support.'];
    }

    /* Rate limited — DNS fallback */
    if ($httpCode === 429) {
        $ok = dnsOnlyCheck($domain);
        return ['valid' => $ok, 'reason' => $ok ? '' : "Email domain <b>$domain</b> does not exist."];
    }

    /* Other non-200 */
    if ($httpCode !== 200) {
        return ['valid' => false, 'reason' => 'Email verification failed. Please try again.'];
    }

    $data = json_decode($response, true);
    if (!$data || !is_array($data)) {
        $ok = dnsOnlyCheck($domain);
        return ['valid' => $ok, 'reason' => $ok ? '' : "Email domain <b>$domain</b> does not exist."];
    }

    $mx          = !empty($data['is_mx_found']['value']);
    $smtp        = !empty($data['is_smtp_valid']['value']);
    $disposable  = !empty($data['is_disposable_email']['value']);
    $deliverable = isset($data['deliverability']) ? strtoupper(trim($data['deliverability'])) : 'UNKNOWN';
    $quality     = isset($data['quality_score']) ? (float) $data['quality_score'] : 0.0;

    if ($disposable)
        return ['valid' => false, 'reason' => 'Disposable/temporary emails are not allowed.'];
    if (!$mx)
        return ['valid' => false, 'reason' => "The domain <b>$domain</b> has no mail server. This email cannot exist."];
    if ($deliverable === 'UNDELIVERABLE')
        return ['valid' => false, 'reason' => 'This email address does not exist or cannot receive mail.'];
    if ($deliverable === 'DELIVERABLE')
        return ['valid' => true, 'reason' => ''];

    /* UNKNOWN — check trusted providers */
    $trustedDomains = [
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'yahoo.in', 'yahoo.co.in', 'ymail.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'icloud.com', 'me.com', 'mac.com',
        'protonmail.com', 'proton.me',
        'rediffmail.com',
    ];

    if (in_array($domain, $trustedDomains, true)) {
        if ($mx && $quality >= 0.50)
            return ['valid' => true, 'reason' => ''];
        return ['valid' => false, 'reason' => 'This email address appears to be invalid.'];
    }

    if ($smtp && $mx && $quality >= 0.70)
        return ['valid' => true, 'reason' => ''];

    return ['valid' => false, 'reason' => 'This email address appears invalid or does not exist.'];
}

/* ══════════════════════════════════════════════════════════
   POST handler
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $mobile   = trim(mysqli_real_escape_string($conn, $_POST['mobile']));
    $password = trim($_POST['password']);

    // ── 1. Empty fields ──
    if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
        $error = "Please fill in all fields.";

    // ── 2. Email format ──
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    // ── 3. Full email existence via Abstract API ──
    } else {
        $emailCheck = validateEmailFull($email);
        if (!$emailCheck['valid']) {
            $error = $emailCheck['reason'];
        }
    }

    if (empty($error)) {
        // ── 4. Mobile ──
        if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            $error = "Please enter a valid 10-digit Indian mobile number (starts with 6–9).";

        // ── 5. Password ──
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";

        } else {
            // ── 6. Duplicate email ──
            $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
            if (mysqli_num_rows($check_email) > 0) {
                $error = "An account with this email already exists.";
            } else {
                // ── 7. Duplicate mobile ──
                $check_mobile = mysqli_query($conn, "SELECT id FROM users WHERE mobile = '$mobile' LIMIT 1");
                if (mysqli_num_rows($check_mobile) > 0) {
                    $error = "An account with this mobile number already exists.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $sql    = "INSERT INTO users (name, email, mobile, password)
                               VALUES ('$name', '$email', '$mobile', '$hashed')";

                    if (mysqli_query($conn, $sql)) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'pranavsuryawanshi4k@gmail.com';
                            $mail->Password   = 'tmzx yxzl ytpn wtyp';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            $mail->setFrom('pranavsuryawanshi4k@gmail.com', 'PrepHub');
                            $mail->addAddress($email, $name);
                            $mail->Subject = 'Welcome to PrepHub — Your Account Has Been Created!';
                            $mail->isHTML(true);

                            $mail->Body = "
                                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;
                                            padding:30px;border:1px solid #e0e0e0;border-radius:16px;
                                            background:#fafafa;'>
                                    <div style='text-align:center;margin-bottom:24px;'>
                                        <h2 style='color:#4f46e5;margin:0;font-size:26px;'>🎓 PrepHub</h2>
                                        <p style='color:#888;font-size:13px;margin:4px 0 0;'>Your Placement Our Responsibility</p>
                                    </div>
                                    <hr style='border:none;border-top:1px solid #eee;margin:0 0 24px;'>
                                    <p style='font-size:16px;color:#333;margin:0 0 8px;'>Hi <strong>$name</strong>! 👋</p>
                                    <p style='font-size:14px;color:#555;margin:0 0 24px;'>
                                        Welcome to <strong style='color:#4f46e5;'>PrepHub</strong>!
                                        Your account has been created successfully.
                                    </p>
                                    <div style='background:#eef2ff;border-radius:12px;padding:20px 24px;margin-bottom:24px;'>
                                        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                                            <tr>
                                                <td style='padding:8px 0;color:#888;width:40%;'>👤 Full Name</td>
                                                <td style='padding:8px 0;color:#333;font-weight:600;'>$name</td>
                                            </tr>
                                            <tr>
                                                <td style='padding:8px 0;color:#888;border-top:1px solid #dde4ff;'>📧 Email</td>
                                                <td style='padding:8px 0;color:#333;font-weight:600;border-top:1px solid #dde4ff;'>$email</td>
                                            </tr>
                                            <tr>
                                                <td style='padding:8px 0;color:#888;border-top:1px solid #dde4ff;'>📱 Mobile</td>
                                                <td style='padding:8px 0;color:#333;font-weight:600;border-top:1px solid #dde4ff;'>+91 $mobile</td>
                                            </tr>
                                            <tr>
                                                <td style='padding:8px 0;color:#888;border-top:1px solid #dde4ff;'>🔑 Password</td>
                                                <td style='padding:8px 0;color:#4f46e5;font-weight:700;
                                                            letter-spacing:2px;border-top:1px solid #dde4ff;'>$password</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div style='background:#fff8e1;border-left:4px solid #f59e0b;
                                                border-radius:8px;padding:12px 16px;margin-bottom:24px;'>
                                        <p style='margin:0;font-size:13px;color:#92400e;'>
                                            ⚠️ <strong>Keep this email safe.</strong>
                                            Do not share your password with anyone.
                                        </p>
                                    </div>
                                    <div style='text-align:center;margin-bottom:24px;'>
                                        <a href='http://localhost/prephub/login.php'
                                           style='display:inline-block;background:#4f46e5;color:#fff;
                                                  text-decoration:none;padding:12px 32px;border-radius:8px;
                                                  font-size:15px;font-weight:bold;'>
                                            🚀 Login to PrepHub
                                        </a>
                                    </div>
                                    <hr style='border:none;border-top:1px solid #eee;margin:0 0 16px;'>
                                    <p style='font-size:11px;color:#bbb;text-align:center;margin:0;'>
                                        If you didn't create this account, please ignore this email.<br>
                                        &copy; PrepHub – All rights reserved.
                                    </p>
                                </div>
                            ";
                            $mail->AltBody = "Hi $name! Welcome to PrepHub. Email: $email | Mobile: +91$mobile | Password: $password.";
                            $mail->send();
                            $success = "Account created successfully! A welcome email has been sent to <b>$email</b>. <a href='login.php'>Login now →</a>";

                        } catch (Exception $e) {
                            $success = "Account created successfully! <a href='login.php'>Login now →</a> (Welcome email could not be sent.)";
                        }
                    } else {
                        $error = "Something went wrong. Please try again.";
                    }
                }
            }
        }
    }

    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PrepHub</title>
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
        max-width: 360px; width: 90%; animation: popIn 0.3s ease;
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
        cursor: pointer; font-size: 14px; font-weight: bold; transition: opacity 0.2s;
      }
      .popup-box button:hover { opacity: 0.85; }
      .popup-error .popup-icon { color: #e74c3c; }
      .popup-error h3          { color: #e74c3c; }
      .popup-error button      { background: #e74c3c; color: #fff; }
      .popup-success .popup-icon { color: #2ecc71; }
      .popup-success h3          { color: #2ecc71; }
      .popup-success button      { background: #4f46e5; color: #fff; }

      .field-wrap { position: relative; margin-bottom: 2px; }
      .field-hint {
        font-size: 12px; margin: 3px 0 10px 4px;
        min-height: 18px; transition: color 0.2s;
        display: flex; align-items: center; gap: 5px;
      }
      .field-hint.ok    { color: #16a34a; }
      .field-hint.error { color: #dc2626; }
      .field-hint.info  { color: #6b7280; }

      input.valid-input   { border-color: #16a34a !important; box-shadow: 0 0 0 2px rgba(22,163,74,0.15) !important; }
      input.invalid-input { border-color: #dc2626 !important; box-shadow: 0 0 0 2px rgba(220,38,38,0.15) !important; }

      .spin {
        display: inline-block; width: 11px; height: 11px;
        border: 2px solid #d1d5db; border-top-color: #4f46e5;
        border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0;
      }
      @keyframes spin { to { transform: rotate(360deg); } }
      #submitBtn:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>

<body class="login-body">

<header>
  <div class="logo">PrepHub</div>
  <nav>
    <a href="index.html">Home</a>
    <a href="login.php">Login</a>
  </nav>
</header>

<div class="form-container">
  <h2>Create Account</h2>

  <form action="register.php" method="POST" id="registerForm" novalidate>

    <div class="field-wrap">
      <input type="text" name="name" id="nameInput" placeholder="Full Name" required autocomplete="off">
    </div>
    <div class="field-hint info" id="nameHint"></div>

    <div class="field-wrap">
      <input type="email" name="email" id="emailInput" placeholder="Email Address" required autocomplete="off">
    </div>
    <div class="field-hint info" id="emailHint"></div>

    <div class="field-wrap">
      <input type="tel" name="mobile" id="mobileInput"
             placeholder="Mobile Number (10 digits)" maxlength="10" required autocomplete="off">
    </div>
    <div class="field-hint info" id="mobileHint"></div>

    <div class="field-wrap">
      <input type="password" name="password" id="passwordInput" placeholder="Password (min 6 chars)" required>
    </div>
    <div class="field-hint info" id="passwordHint"></div>

    <button type="submit" id="submitBtn">Register</button>
    <p>Already have an account? <a href="login.php">Login</a></p>
  </form>
</div>

<!-- ERROR POPUP -->
<?php if (!empty($error)): ?>
<div class="popup-overlay show" id="errorPopup">
  <div class="popup-box popup-error">
    <div class="popup-icon">❌</div>
    <h3>Oops!</h3>
    <p><?php echo $error; ?></p>
    <button onclick="document.getElementById('errorPopup').classList.remove('show')">Try Again</button>
  </div>
</div>
<?php endif; ?>

<!-- SUCCESS POPUP -->
<?php if (!empty($success)): ?>
<div class="popup-overlay show" id="successPopup">
  <div class="popup-box popup-success">
    <div class="popup-icon">🎉</div>
    <h3>Welcome to PrepHub!</h3>
    <p><?php echo $success; ?></p>
    <button onclick="window.location.href='login.php'">Login Now →</button>
  </div>
</div>
<?php endif; ?>

<script>
function setHint(id, html, type) {
  var el = document.getElementById(id);
  el.innerHTML = html;
  el.className = 'field-hint ' + type;
}
function setInputState(el, state) {
  el.classList.remove('valid-input', 'invalid-input');
  if (state === 'ok')    el.classList.add('valid-input');
  if (state === 'error') el.classList.add('invalid-input');
}
function isEmailFormat(e) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e);
}

/* ── Name ── */
document.getElementById('nameInput').addEventListener('input', function () {
  var v = this.value.trim();
  if (!v)           { setHint('nameHint', '', 'info');                       setInputState(this, '');      return; }
  if (v.length < 2) { setHint('nameHint', '❌ Name is too short.', 'error'); setInputState(this, 'error'); return; }
  setHint('nameHint', '✅ Looks good!', 'ok');
  setInputState(this, 'ok');
});

/* ── Mobile ── */
document.getElementById('mobileInput').addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
  var v = this.value;
  if (!v)                 { setHint('mobileHint', '', 'info');                                             setInputState(this, '');      return; }
  if (v.length < 10)      { setHint('mobileHint', '❌ Must be 10 digits (' + v.length + '/10).', 'error'); setInputState(this, 'error'); return; }
  if (!/^[6-9]/.test(v)) { setHint('mobileHint', '❌ Must start with 6, 7, 8 or 9.', 'error');            setInputState(this, 'error'); return; }
  setHint('mobileHint', '✅ Valid mobile number.', 'ok');
  setInputState(this, 'ok');
});

/* ── Password ── */
document.getElementById('passwordInput').addEventListener('input', function () {
  var v = this.value;
  if (!v)           { setHint('passwordHint', '', 'info');                                                      setInputState(this, '');      return; }
  if (v.length < 6) { setHint('passwordHint', '❌ At least 6 characters (' + v.length + ' so far).', 'error'); setInputState(this, 'error'); return; }
  var strong = v.length >= 10 && /[A-Z]/.test(v) && /[0-9]/.test(v);
  setHint('passwordHint', (strong ? '💪 Strong' : '✅ Acceptable') + ' password.', 'ok');
  setInputState(this, 'ok');
});

/* ── Email — live verify via check_email_domain.php ── */
var emailInput = document.getElementById('emailInput');
var emailTimer = null;
var emailState = 'idle'; // idle | checking | ok | error

emailInput.addEventListener('input', function () {
  clearTimeout(emailTimer);
  emailState = 'idle';
  var v = this.value.trim();

  if (!v) { setHint('emailHint', '', 'info'); setInputState(this, ''); return; }

  if (!isEmailFormat(v)) {
    emailState = 'error';
    setHint('emailHint', '❌ Enter a valid email (e.g. user@gmail.com).', 'error');
    setInputState(this, 'error');
    return;
  }

  setHint('emailHint', '<span class="spin"></span> Verifying email address…', 'info');
  setInputState(this, '');
  emailState = 'checking';

  var self = this;
  emailTimer = setTimeout(function () { verifyEmail(v, self); }, 900);
});

emailInput.addEventListener('blur', function () {
  clearTimeout(emailTimer);
  var v = this.value.trim();
  if (v && isEmailFormat(v) && emailState === 'checking') {
    verifyEmail(v, this);
  }
});

function verifyEmail(email, inputEl) {
  emailState = 'checking';
  setHint('emailHint', '<span class="spin"></span> Verifying email address…', 'info');

  fetch('check_email_domain.php?email=' + encodeURIComponent(email))
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      if (data.valid) {
        emailState = 'ok';
        setHint('emailHint', '✅ ' + (data.reason || 'Email verified.'), 'ok');
        setInputState(inputEl, 'ok');
      } else {
        emailState = 'error';
        setHint('emailHint', '❌ ' + (data.reason || 'This email does not exist.'), 'error');
        setInputState(inputEl, 'error');
      }
    })
    .catch(function () {
      /* Network error in JS — let server-side check handle it on submit */
      emailState = 'ok';
      setHint('emailHint', '⚠️ Could not verify now — server will check on submit.', 'info');
      setInputState(inputEl, '');
    });
}

/* ── Submit guard ── */
document.getElementById('registerForm').addEventListener('submit', function (e) {
  var name     = document.getElementById('nameInput').value.trim();
  var email    = document.getElementById('emailInput').value.trim();
  var mobile   = document.getElementById('mobileInput').value.trim();
  var password = document.getElementById('passwordInput').value;

  if (emailState === 'checking') {
    e.preventDefault();
    setHint('emailHint', '<span class="spin"></span> Please wait — still verifying email…', 'info');
    return;
  }

  var errors = [];
  if (name.length < 2)                  errors.push('Enter a valid full name.');
  if (!isEmailFormat(email))            errors.push('Enter a valid email address.');
  if (emailState === 'error')           errors.push('Use a real, existing email address.');
  if (!/^[6-9][0-9]{9}$/.test(mobile)) errors.push('Enter a valid 10-digit mobile number starting with 6–9.');
  if (password.length < 6)             errors.push('Password must be at least 6 characters.');

  if (errors.length > 0) {
    e.preventDefault();
    alert('Please fix:\n\n• ' + errors.join('\n• '));
    return;
  }

  var btn = document.getElementById('submitBtn');
  btn.disabled    = true;
  btn.textContent = 'Creating account…';
});
</script>
</body>
</html>