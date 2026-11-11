<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Transacao.php';

/** RF15/RF16 — INSUMO. */
class InsumoRepository
{
    public static function listar(string $busca = '', bool $somenteBaixo = false): array
    {
        $where = [];
        $params = [];
        if ($busca !== '') {
            $where[] = 'nome LIKE :busca';
            $params['busca'] = '%' . addcslashes($busca, '%_\\') . '%';
        }
        if ($somenteBaixo) {
            $where[] = 'estoque_atual < estoque_minimo';
        }
        $stmt = Database::getConnection()->prepare(
            'SELECT id_insumo, nome, unidade_medida, estoque_atual, estoque_minimo, custo_unitario FROM insumo'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY nome'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function buscar(int $id, bool $travar = false): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM insumo WHERE id_insumo = :id' . ($travar ? Transacao::travar() : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function nomeExiste(string $nome, int $ignorarId = 0): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM insumo WHERE LOWER(nome) = LOWER(:n) AND id_insumo <> :id');
        $stmt->execute(['n' => $nome, 'id' => $ignorarId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function inserir(array $d): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO insumo (nome, unidade_medida, estoque_atual, estoque_minimo, custo_unitario) VALUES (:nome, :un, :atual, :minimo, :custo)'
        )->execute(['nome' => $d['nome'], 'un' => $d['unidade_medida'], 'atual' => $d['estoque_atual'], 'minimo' => $d['estoque_minimo'], 'custo' => $d['custo_unitario']]);
        return (int) $pdo->lastInsertId();
    }

    /** Edita cadastro (nunca o saldo: saldo só muda por movimentação). */
    public static function atualizar(int $id, array $d): void
    {
        Database::getConnection()->prepare(
            'UPDATE insumo SET nome = :nome, unidade_medida = :un, estoque_minimo = :minimo, custo_unitario = :custo WHERE id_insumo = :id'
        )->execute(['nome' => $d['nome'], 'un' => $d['unidade_medida'], 'minimo' => $d['estoque_minimo'], 'custo' => $d['custo_unitario'], 'id' => $id]);
    }

    public static function somarEstoque(int $id, float $delta): void
    {
        Database::getConnection()->prepare('UPDATE insumo SET estoque_atual = estoque_atual + :d WHERE id_insumo = :id')
            ->execute(['d' => $delta, 'id' => $id]);
    }

    public static function usadoEmFicha(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM produto_insumo WHERE id_insumo = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function excluir(int $id): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM movimento_estoque WHERE id_insumo = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM insumo WHERE id_insumo = :id')->execute(['id' => $id]);
    }

    /** Consumo de insumos de um pedido pela ficha técnica: [id_insumo => quantidade]. */
    public static function consumoDoPedido(int $idPedido): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT pi.id_insumo, SUM(ip.quantidade * pi.quantidade_utilizada) AS total
             FROM item_pedido ip INNER JOIN produto_insumo pi ON pi.id_produto = ip.id_produto
             WHERE ip.id_pedido = :id GROUP BY pi.id_insumo'
        );
        $stmt->execute(['id' => $idPedido]);
        $consumo = [];
        foreach ($stmt->fetchAll() as $l) {
            $consumo[(int) $l['id_insumo']] = (float) $l['total'];
        }
        return $consumo;
    }
}
