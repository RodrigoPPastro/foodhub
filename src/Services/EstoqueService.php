<?php

require_once __DIR__ . '/../Repositories/InsumoRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/../Models/TipoMovimento.php';
require_once __DIR__ . '/InsumoService.php';

/**
 * RF15 — baixa automática de estoque ao confirmar um pedido (e estorno se ele for
 * cancelado depois). Chamar DENTRO da transação que muda o status do pedido.
 */
class EstoqueService
{
    /** Debita da ficha técnica de cada item. Falha (e desfaz tudo) se faltar insumo. */
    public static function baixarPorPedido(int $idPedido, ?int $idUsuario = null): void
    {
        self::aplicar($idPedido, TipoMovimento::SAIDA, "Baixa automática — pedido #$idPedido", $idUsuario);
    }

    /** Devolve ao estoque o que foi baixado (pedido cancelado após a confirmação). */
    public static function estornarPorPedido(int $idPedido, ?int $idUsuario = null): void
    {
        self::aplicar($idPedido, TipoMovimento::ENTRADA, "Estorno — pedido #$idPedido cancelado", $idUsuario);
    }

    private static function aplicar(int $idPedido, string $tipo, string $motivo, ?int $idUsuario): void
    {
        $consumo = InsumoRepository::consumoDoPedido($idPedido);
        ksort($consumo); // ordem fixa evita deadlock entre pedidos concorrentes
        Transacao::executar(function () use ($consumo, $tipo, $motivo, $idUsuario) {
            foreach ($consumo as $idInsumo => $qtd) {
                if ($qtd > 0) {
                    InsumoService::aplicarMovimento((int) $idInsumo, $tipo, round($qtd, 3), $motivo, $idUsuario);
                }
            }
        });
    }
}
