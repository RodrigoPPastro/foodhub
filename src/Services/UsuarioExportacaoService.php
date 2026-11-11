<?php

require_once __DIR__ . '/../Repositories/UsuarioExportacaoRepository.php';
require_once __DIR__ . '/PdfService.php';
require_once __DIR__ . '/PeriodoService.php';

/**
 * RF26 (Desafio Plus) — exportação da lista de usuários em PDF.
 */
class UsuarioExportacaoService
{
    /** @return array{conteudo:string,arquivo:string} */
    public static function gerarPdf(string $geradoPor): array
    {
        $usuarios = UsuarioExportacaoRepository::listarTodos();

        return [
            'conteudo' => PdfService::listaUsuarios($usuarios, $geradoPor),
            'arquivo'  => 'usuarios_foodhub_' . PeriodoService::hoje() . '.pdf',
        ];
    }
}
