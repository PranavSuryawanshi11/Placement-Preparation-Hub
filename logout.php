<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
<title>Logout</title>
<link rel="stylesheet" href="style.css">
<style>
  /* Popup Overlay */
  .popup-overlay {
    display: flex;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    justify-content: center;
    align-items: center;
  }

  /* Popup Box */
  .popup-box {
    background: #fff;
    border-radius: 12px;
    padding: 30px 40px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    max-width: 320px;
    width: 90%;
    animation: popIn 0.3s ease;
  }
  @keyframes popIn {
    from { transform: scale(0.8); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
  }

  .popup-icon {
    font-size: 48px;
    margin-bottom: 12px;
    color: #3498db;
  }
  .popup-box h3 {
    margin: 0 0 8px;
    font-size: 20px;
    color: #3498db;
  }
  .popup-box p {
    margin: 0 0 20px;
    color: #555;
    font-size: 14px;
  }
  .popup-box button {
    padding: 10px 28px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    background: #3498db;
    color: #fff;
  }
  .popup-box button:hover {
    background: #2980b9;
  }
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

<!-- LOGOUT SUCCESS POPUP -->
<div class="popup-overlay">
  <div class="popup-box">
    <div class="popup-icon">👋</div>
    <h3>Logged Out!</h3>
    <p>You have been successfully logged out. Redirecting to Login...</p>
    <button onclick="window.location.href='login.php'">Go to Login</button>
  </div>
</div>

<!-- Auto redirect after 2 seconds -->
<script>
  setTimeout(function() {
    window.location.href = 'index.html';
  }, 2000);
</script>

</body>
</html>