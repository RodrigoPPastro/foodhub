<?php

/**
 * Valores de PEDIDO.status usados pelo módulo financeiro.
 * ATENÇÃO: confirmar com Pedro/Fernanda que o script SQL grava exatamente
 * estas strings. Se mudarem, o ajuste é só aqui.
 *
 * Regra do financeiro: "venda válida" = qualquer pedido que NÃO está cancelado.
 */
class PedidoStatus
{
    public const NOVO       = 'novo';
    public const EM_PREPARO = 'em preparo';
    public const PRONTO     = 'pronto';
    public const SAIU       = 'saiu';
    public const ENTREGUE   = 'entregue';
    public const CANCELADO  = 'cancelado';

    /** Pedidos "em aberto" (ainda não finalizados) — indicador do dashboard. */
    public const EM_ABERTO = [self::NOVO, self::EM_PREPARO, self::PRONTO, self::SAIU];
}
