<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/InsumoService.php';

/** RF15/RF16 */
class InsumoController
{
    public static function listar(array $get): array { return RespostaController::executar(fn() => InsumoService::listar($get)); }
    public static function salvar(array $e, ?int $u): array { return RespostaController::executar(fn() => InsumoService::salvar($e, $u)); }
    public static function excluir(array $e): array { return RespostaController::executar(fn() => InsumoService::excluir((int) ($e['id_insumo'] ?? 0))); }
    public static function movimentar(array $e, ?int $u): array { return RespostaController::executar(fn() => InsumoService::movimentar($e, $u)); }
    public static function movimentos(array $get): array { return RespostaController::executar(fn() => InsumoService::movimentos($get)); }
}
