<?php

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/session.php';

Session::start();
Session::destroy();

header('Location: login.php');
exit;
