<?php
/** Janela de avisos do Master (estoque, contas a pagar e a receber). GET → pendências + frase para leitura em voz. */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/AvisoService.php';
require_once __DIR__ . '/../../src/Controllers/RespostaController.php';

ApiGuard::exigirMetodo('GET');
ApiGuard::requireMaster();
ApiGuard::responder(RespostaController::executar(fn() => AvisoService::pendencias()));
