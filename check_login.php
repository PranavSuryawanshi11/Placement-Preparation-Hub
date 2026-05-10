<?php
session_start();

header('Content-Type: application/json');

// Prevent caching so browser always gets fresh login status
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => true,
        'name'      => $_SESSION['user_name']  ?? '',
        'email'     => $_SESSION['user_email'] ?? ''
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
?>