<?php

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once __DIR__ . '/../config/session.php';

Session::start();

if (!Session::isAuthenticated()) {
    Session::set('redirect_after_login', $_SERVER['REQUEST_URI']);
    header('Location: login.php');
    exit;
}

$GLOBALS['admin_user'] = $_SESSION['admin_user'] ?? [];
