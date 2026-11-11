<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';

/**
 * RF23 — agregações do caixa (por data do PAGAMENTO: dinheiro efetivamente
 * recebido no dia) e persistência do fechamento.
 *
 * Obs.: com ATTR_EMULATE_PREPARES=false o MySQL NÃO aceita o mesmo
 * placeholder nomeado duas vezes na query — por isso :de/:ate nunca repetem.
 */
class FechamentoCaixaRepository
{
    /** @return array<int,array{forma:string,total:string}> */
    public static function totaisPorFormaPagamento(string $de, string $ate): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT f.descricao AS forma, COALESCE(SUM(pg.valor), 0) AS total
             FROM pagamento pg
             INNER JOIN pedido p ON p.id_pedido = pg.id_pedido
             INNER JOIN forma_pagamento f ON f.id_forma_pagamento = pg.id_forma_pagamento
             WHERE pg.data_hora >= :de AND pg.data_hora < :ate AND p.status <> :cancelado
             GROUP BY f.descricao'
        );
        $stmt->execute(['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array{canal:string,total:string,pedidos:string}> */
    public static function totaisPorCanal(string $de, string $ate): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.canal, COALESCE(SUM(pg.valor), 0) AS total,
                    COUNT(DISTINCT p.id_pedido) AS pedidos
             FROM pagamento pg
             INNER JOIN pedido p ON p.id_pedido = pg.id_pedido
             WHERE pg.data_hora >= :de AND pg.data_hora < :ate AND p.status <> :cancelado
             GROUP BY p.canal'
        );
        $stmt->execute(['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]);
        return $stmt->fetchAll();
    }

    /** Pedidos do dia (não cancelados) cujo pagamento ainda não cobre o valor_total. */
    public static function pedidosComPagamentoPendente(string $de, string $ate): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.id_pedido, p.valor_total, COALESCE(SUM(pg.valor), 0) AS pago
             FROM pedido p
             LEFT JOIN pagamento pg ON pg.id_pedido = p.id_pedido
             WHERE p.data_hora >= :de AND p.data_hora < :ate AND p.status <> :cancelado
             GROUP BY p.id_pedido, p.valor_total
             HAVING COALESCE(SUM(pg.valor), 0) < p.valor_total
             ORDER BY p.id_pedido'
        );
        $stmt->execute(['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]);
        return $stmt->fetchAll();
    }

    public static function buscarPorData(string $data): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT fc.*, u.nome_completo AS responsavel
             FROM fechamento_caixa fc
             LEFT JOIN usuario u ON u.id_usuario = fc.id_usuario_responsavel
             WHERE fc.data_referencia = :data
             LIMIT 1'
        );
        $stmt->execute(['data' => $data]);
        $linha = $stmt->fetch();
        return $linha ?: null;
    }

    /** @param array<string,mixed> $dados  valores monetários já em string decimal */
    public static function inserir(array $dados): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO fechamento_caixa
                (id_usuario_responsavel, data_referencia, total_vendas, total_dinheiro,
                 total_cartao, total_pix, total_delivery, observacoes, detalhamento)
             VALUES
                (:usuario, :data, :vendas, :dinheiro, :cartao, :pix, :delivery, :obs, :detalhe)'
        );
        $stmt->execute([
            'usuario'  => $dados['id_usuario'],
            'data'     => $dados['data_referencia'],
            'vendas'   => $dados['total_vendas'],
            'dinheiro' => $dados['total_dinheiro'],
            'cartao'   => $dados['total_cartao'],
            'pix'      => $dados['total_pix'],
            'delivery' => $dados['total_delivery'],
            'obs'      => $dados['observacoes'],
            'detalhe'  => $dados['detalhamento'],
        ]);
        return (int) $pdo->lastInsertId();
    }
}
