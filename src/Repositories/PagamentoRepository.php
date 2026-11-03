<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * RF22 — acesso a dados de PAGAMENTO / FORMA_PAGAMENTO.
 */
class PagamentoRepository
{
    /** Executa $acao numa transação; qualquer exceção faz rollback e é relançada. */
    public static function executarEmTransacao(callable $acao)
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $resultado = $acao();
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Deve ser chamado DENTRO de transação. No MySQL trava a linha do pedido
     * (FOR UPDATE) para que dois pagamentos simultâneos não estourem o saldo.
     */
    public static function buscarPedidoParaPagamento(int $idPedido): ?array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT id_pedido, valor_total, status FROM pedido WHERE id_pedido = :id';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $idPedido]);
        $pedido = $stmt->fetch();
        return $pedido ?: null;
    }

    /** Soma já paga do pedido (string DECIMAL; "0" se nada pago). */
    public static function totalPagoDoPedido(int $idPedido): string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM pagamento WHERE id_pedido = :id'
        );
        $stmt->execute(['id' => $idPedido]);
        return (string) $stmt->fetchColumn();
    }

    public static function buscarIdForma(string $descricao): ?int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_forma_pagamento FROM forma_pagamento WHERE descricao = :d LIMIT 1'
        );
        $stmt->execute(['d' => $descricao]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function inserir(int $idPedido, int $idForma, string $valorDecimal, string $dataHora): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO pagamento (id_pedido, id_forma_pagamento, valor, data_hora)
             VALUES (:pedido, :forma, :valor, :data_hora)'
        );
        $stmt->execute([
            'pedido'    => $idPedido,
            'forma'     => $idForma,
            'valor'     => $valorDecimal,
            'data_hora' => $dataHora,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarPorPedido(int $idPedido): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT pg.id_pagamento, f.descricao AS forma, pg.valor, pg.data_hora
             FROM pagamento pg
             INNER JOIN forma_pagamento f ON f.id_forma_pagamento = pg.id_forma_pagamento
             WHERE pg.id_pedido = :id
             ORDER BY pg.data_hora, pg.id_pagamento'
        );
        $stmt->execute(['id' => $idPedido]);
        return $stmt->fetchAll();
    }

    public static function buscarPedido(int $idPedido): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_pedido, valor_total, status FROM pedido WHERE id_pedido = :id'
        );
        $stmt->execute(['id' => $idPedido]);
        $pedido = $stmt->fetch();
        return $pedido ?: null;
    }
}
