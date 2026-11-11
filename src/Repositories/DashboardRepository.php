<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';

/**
 * RF25 — indicadores que não são agregações de período
 * (as de período reutilizam RelatorioRepository/FechamentoCaixaRepository).
 */
class DashboardRepository
{
    public static function contarPedidosEmAberto(): int
    {
        $status = PedidoStatus::EM_ABERTO;
        $marcadores = implode(',', array_fill(0, count($status), '?')); // só '?' gerados aqui
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM pedido WHERE status IN ($marcadores)"
        );
        $stmt->execute($status);
        return (int) $stmt->fetchColumn();
    }

    /** RF16 — insumos abaixo do mínimo, os mais críticos primeiro. */
    public static function insumosAbaixoDoMinimo(): array
    {
        return Database::getConnection()->query(
            'SELECT id_insumo, nome, unidade_medida, estoque_atual, estoque_minimo
             FROM insumo
             WHERE estoque_atual < estoque_minimo
             ORDER BY (estoque_minimo - estoque_atual) DESC, nome'
        )->fetchAll();
    }
}
