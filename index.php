<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header('Location: ' . ($role === 'student' ? 'student/dashboard.php' : 'admin/dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
