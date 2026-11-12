<?php

require_once __DIR__ . '/../../config/database.php';

/** Acesso a dados dos garçons e da taxa de serviço (10%). */
class GorjetaRepository
{
    public static function garcons(bool $soAtivos): array
    {
        $sql = 'SELECT id_garcom, nome, ativo FROM garcom' . ($soAtivos ? ' WHERE ativo = 1' : '') . ' ORDER BY nome';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function buscarGarcom(int $id): ?array
    {
        $st = Database::getConnection()->prepare('SELECT * FROM garcom WHERE id_garcom = :i');
        $st->execute(['i' => $id]);
        return $st->fetch() ?: null;
    }

    public static function inserirGarcom(string $nome): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare('INSERT INTO garcom (nome) VALUES (:n)')->execute(['n' => $nome]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizarGarcom(int $id, string $nome, bool $ativo): void
    {
        Database::getConnection()->prepare('UPDATE garcom SET nome = :n, ativo = :a WHERE id_garcom = :i')
            ->execute(['n' => $nome, 'a' => $ativo ? 1 : 0, 'i' => $id]);
    }

    /** Lançamentos de taxa no período [de, ate) — opcionalmente de um garçom e/ou só pendentes. */
    public static function lancamentos(string $de, string $ate, ?int $idGarcom = null, ?string $status = null): array
    {
        $sql = 'SELECT t.id_taxa, t.id_mesa, t.id_garcom, g.nome AS garcom, t.base, t.percentual, t.valor,
                       t.status, t.data_hora, t.data_pagamento, m.numero AS mesa
                  FROM taxa_servico t
             LEFT JOIN garcom g ON g.id_garcom = t.id_garcom
             LEFT JOIN mesa m ON m.id_mesa = t.id_mesa
                 WHERE t.data_hora >= :de AND t.data_hora < :ate';
        $p = ['de' => $de, 'ate' => $ate];
        if ($idGarcom !== null) { $sql .= ' AND t.id_garcom = :g'; $p['g'] = $idGarcom; }
        if ($status !== null)   { $sql .= ' AND t.status = :s';    $p['s'] = $status; }
        $st = Database::getConnection()->prepare($sql . ' ORDER BY t.data_hora DESC, t.id_taxa DESC');
        $st->execute($p);
        return $st->fetchAll();
    }

    /** Marca como pagas as taxas pendentes (travadas na transação). Retorna quantas mudaram. */
    public static function marcarPagas(array $ids, ?int $idUsuario): int
    {
        if ($ids === []) { return 0; }
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $st = Database::getConnection()->prepare(
            "UPDATE taxa_servico SET status = 'pago', data_pagamento = NOW(), id_usuario_pagou = ?
              WHERE status = 'pendente' AND id_taxa IN ($marc)"
        );
        $st->execute(array_merge([$idUsuario], array_map('intval', $ids)));
        return $st->rowCount();
    }
}
