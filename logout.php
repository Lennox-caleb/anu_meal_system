<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    logAction($conn, 'User Logout', "User {$_SESSION['username']} logged out.");
}
session_destroy();
header('Location: login.php');
exit;
