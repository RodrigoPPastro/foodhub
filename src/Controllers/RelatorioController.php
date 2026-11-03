<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/RelatorioService.php';

/** RF24 */
class RelatorioController
{
    public static function gerar(array $get): array
    {
        return RespostaController::executar(fn() => RelatorioService::gerar(
            (string) ($get['relatorio'] ?? ''),
            isset($get['inicio']) ? (string) $get['inicio'] : null,
            isset($get['fim']) ? (string) $get['fim'] : null,
            (int) ($get['limite'] ?? 10)
        ));
    }
}
