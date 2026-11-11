<?php

require_once __DIR__ . '/../../config/database.php';

/** RF15 — histórico de MOVIMENTO_ESTOQUE. */
class MovimentoEstoqueRepository
{
    public static function inserir(int $idInsumo, ?int $idUsuario, string $tipo, float $quantidade, ?string $motivo, string $dataHora): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO movimento_estoque (id_insumo, id_usuario, tipo, quantidade, data_hora, motivo) VALUES (:i, :u, :t, :q, :d, :m)'
        )->execute(['i' => $idInsumo, 'u' => $idUsuario, 't' => $tipo, 'q' => $quantidade, 'd' => $dataHora, 'm' => $motivo]);
        return (int) $pdo->lastInsertId();
    }

    public static function listar(int $idInsumo = 0, int $limite = 50): array
    {
        $sql = 'SELECT m.id_movimento, m.id_insumo, i.nome AS insumo, i.unidade_medida, m.tipo, m.quantidade,
                       m.data_hora, m.motivo, u.nome_completo AS usuario
                FROM movimento_estoque m INNER JOIN insumo i ON i.id_insumo = m.id_insumo
                LEFT JOIN usuario u ON u.id_usuario = m.id_usuario'
            . ($idInsumo > 0 ? ' WHERE m.id_insumo = :id' : '') . ' ORDER BY m.data_hora DESC, m.id_movimento DESC LIMIT :limite';
        $stmt = Database::getConnection()->prepare($sql);
        if ($idInsumo > 0) {
            $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
