<?php
require_once '../backend/config.php';

// Logout user
AuthMiddleware::logout();

$_SESSION['success'] = "You have been logged out successfully.";
redirect('/login.php');
?>
