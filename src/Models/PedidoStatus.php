<?php

require_once __DIR__ . '/CanalVenda.php';

/**
 * Valores de PEDIDO.status (RF17) e regras do fluxo do kanban.
 *   novo → em preparo → pronto → saiu → entregue      (canais de delivery)
 *   novo → em preparo → pronto → entregue             (balcão / mesa)
 *   qualquer etapa antes de "entregue" → cancelado
 * Regra do financeiro: "venda válida" = pedido que NÃO está cancelado.
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
    public const TODOS = [self::NOVO, self::EM_PREPARO, self::PRONTO, self::SAIU, self::ENTREGUE, self::CANCELADO];

    public const ROTULOS = [
        self::NOVO => 'Novo', self::EM_PREPARO => 'Em preparo', self::PRONTO => 'Pronto',
        self::SAIU => 'Saiu para entrega', self::ENTREGUE => 'Entregue', self::CANCELADO => 'Cancelado',
    ];

    private const FLUXO = [
        self::NOVO       => [self::EM_PREPARO, self::CANCELADO],
        self::EM_PREPARO => [self::PRONTO, self::CANCELADO],
        self::PRONTO     => [self::SAIU, self::ENTREGUE, self::CANCELADO],
        self::SAIU       => [self::ENTREGUE, self::CANCELADO],
        self::ENTREGUE   => [],
        self::CANCELADO  => [],
    ];

    public static function rotulo(string $status): string
    {
        return self::ROTULOS[$status] ?? ucfirst($status);
    }

    /** Próximos status permitidos considerando o canal (presencial x delivery). */
    public static function proximos(string $atual, string $canal): array
    {
        $presencial = CanalVenda::ehPresencial($canal);
        return array_values(array_filter(self::FLUXO[$atual] ?? [], function (string $para) use ($atual, $presencial) {
            if ($atual === self::PRONTO && $para === self::SAIU) {
                return !$presencial;       // só delivery sai para entrega
            }
            if ($atual === self::PRONTO && $para === self::ENTREGUE) {
                return $presencial;        // delivery precisa passar por "saiu"
            }
            return true;
        }));
    }

    public static function podeTransicionar(string $de, string $para, string $canal): bool
    {
        return in_array($para, self::proximos($de, $canal), true);
    }
}
