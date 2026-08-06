<?php
/**
 * UrbanNutMix - User Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Unset user sessions
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_logged_in']);

// Redirect to login page
header("Location: login.php");
exit;
