<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/UsuarioExportacaoService.php';

/** RF26 (Desafio Plus) */
class UsuarioExportacaoController
{
    /** dados => ['conteudo'=>bytes, 'arquivo'=>nome] */
    public static function pdf(string $geradoPor): array
    {
        return RespostaController::executar(fn() => UsuarioExportacaoService::gerarPdf($geradoPor));
    }
}
