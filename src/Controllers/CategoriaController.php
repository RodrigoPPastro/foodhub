<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/CategoriaService.php';

/** RF13 */
class CategoriaController
{
    public static function listar(): array { return RespostaController::executar(fn() => CategoriaService::listar()); }
    public static function salvar(array $e): array { return RespostaController::executar(fn() => CategoriaService::salvar($e)); }
    public static function excluir(array $e): array { return RespostaController::executar(fn() => CategoriaService::excluir((int) ($e['id_categoria'] ?? 0))); }
}
