<?php
require_once __DIR__ . '/../src/Services/SessionService.php';

SessionService::start();
SessionService::logout();

header('Location: /foodhub/public/login.php');
exit;
