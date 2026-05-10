<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your phpMyAdmin username
define('DB_PASS', '');           // your phpMyAdmin password
define('DB_NAME', 'prephub');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>