<?php
/** http://localhost/foodhub/public → tela certa para quem chega: login, ou o início do sistema se já estiver logado. */
require_once __DIR__ . '/../src/Services/SessionService.php';

SessionService::start();
header('Location: ' . (SessionService::estaAutenticado() ? 'dashboard.php' : 'login.php'));
exit;
