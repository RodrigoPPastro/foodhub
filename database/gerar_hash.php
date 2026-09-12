<?php
/**
 * Utilitário de linha de comando pra gerar o hash da senha do usuário
 * master (que, pelo enunciado, é criado direto no banco via script SQL).
 *
 * Uso:  php gerar_hash.php "Master1!"
 */
require_once __DIR__ . '/../src/Services/PasswordService.php';

$senha = $argv[1] ?? null;

if ($senha === null) {
    echo "Uso: php gerar_hash.php \"suaSenha\"\n";
    exit(1);
}

echo PasswordService::hash($senha) . "\n";
