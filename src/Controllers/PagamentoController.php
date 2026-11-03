<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/PagamentoService.php';

/** RF22 */
class PagamentoController
{
    /** @param array $entrada ['id_pedido'=>int, 'pagamentos'=>[['forma'=>..,'valor'=>..],...]] */
    public static function registrar(array $entrada): array
    {
        return RespostaController::executar(fn() => PagamentoService::registrarMultiplos(
            (int) ($entrada['id_pedido'] ?? 0),
            is_array($entrada['pagamentos'] ?? null) ? $entrada['pagamentos'] : []
        ));
    }

    public static function consultar(array $get): array
    {
        return RespostaController::executar(
            fn() => PagamentoService::consultarPorPedido((int) ($get['id_pedido'] ?? 0))
        );
    }
}
