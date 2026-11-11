<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Transacao.php';

/** RF21 — ENTREGADOR. */
class EntregadorRepository
{
    public static function listar(): array
    {
        return Database::getConnection()->query(
            "SELECT e.id_entregador, e.nome, e.telefone, e.veiculo, e.status,
                    (SELECT COUNT(*) FROM pedido p WHERE p.id_entregador = e.id_entregador AND p.status = 'saiu') AS entregas_em_andamento,
                    (SELECT COUNT(*) FROM pedido p WHERE p.id_entregador = e.id_entregador) AS total_pedidos
             FROM entregador e ORDER BY e.nome"
        )->fetchAll();
    }

    public static function buscar(int $id, bool $travar = false): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM entregador WHERE id_entregador = :id' . ($travar ? Transacao::travar() : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function inserir(array $d): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare('INSERT INTO entregador (nome, telefone, veiculo, status) VALUES (:n, :t, :v, :s)')
            ->execute(['n' => $d['nome'], 't' => $d['telefone'], 'v' => $d['veiculo'], 's' => $d['status']]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, array $d): void
    {
        Database::getConnection()->prepare('UPDATE entregador SET nome = :n, telefone = :t, veiculo = :v, status = :s WHERE id_entregador = :id')
            ->execute(['n' => $d['nome'], 't' => $d['telefone'], 'v' => $d['veiculo'], 's' => $d['status'], 'id' => $id]);
    }

    public static function definirStatus(int $id, string $status): void
    {
        Database::getConnection()->prepare('UPDATE entregador SET status = :s WHERE id_entregador = :id')->execute(['s' => $status, 'id' => $id]);
    }

    public static function temPedidos(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM pedido WHERE id_entregador = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function excluir(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM entregador WHERE id_entregador = :id')->execute(['id' => $id]);
    }
}
