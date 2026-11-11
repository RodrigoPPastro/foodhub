<?php
/**
 * Endpoint AJAX pro front-end (Dandara) chamar quando o usuário digitar o CEP.
 * GET /public/api/cep.php?cep=20000000  ->  JSON com endereço ou {"erro": true}
 */
require_once __DIR__ . '/../../src/Services/CepService.php';

header('Content-Type: application/json; charset=utf-8');

$cep = $_GET['cep'] ?? '';
$endereco = CepService::consultar($cep);

if ($endereco === null) {
    http_response_code(404);
    echo json_encode(['erro' => true, 'mensagem' => 'CEP não encontrado. Preencha manualmente.']);
    exit;
}

echo json_encode($endereco);
