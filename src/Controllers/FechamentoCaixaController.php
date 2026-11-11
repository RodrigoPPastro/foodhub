<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/FechamentoCaixaService.php';

/** RF23 */
class FechamentoCaixaController
{
    public static function consultar(array $get): array
    {
        return RespostaController::executar(
            fn() => FechamentoCaixaService::consultar((string) ($get['data'] ?? ''))
        );
    }

    public static function fechar(int $idUsuario, array $entrada): array
    {
        return RespostaController::executar(fn() => FechamentoCaixaService::fechar(
            $idUsuario,
            (string) ($entrada['data'] ?? ''),
            isset($entrada['observacoes']) ? (string) $entrada['observacoes'] : null
        ));
    }

    /** dados => ['conteudo'=>bytes, 'arquivo'=>nome] */
    public static function pdf(array $get): array
    {
        return RespostaController::executar(
            fn() => FechamentoCaixaService::gerarPdf((string) ($get['data'] ?? ''))
        );
    }
}
