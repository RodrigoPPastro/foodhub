<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';

/**
 * RF24/RF25 — consultas de agregação para relatórios e dashboard.
 * Venda válida = pedido não cancelado, filtrado por PEDIDO.data_hora.
 */
class RelatorioRepository
{
    /** @return array{pedidos:string,total:string} */
    public static function resumo(string $de, string $ate): array
    {
        $stmt = self::executar(
            'SELECT COUNT(*) AS pedidos, COALESCE(SUM(valor_total), 0) AS total
             FROM pedido
             WHERE data_hora >= :de AND data_hora < :ate AND status <> :cancelado',
            ['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]
        );
        return $stmt->fetch();
    }

    /** Vendas agrupadas por dia (dias sem venda não aparecem; o Service preenche). */
    public static function vendasPorDia(string $de, string $ate): array
    {
        return self::executar(
            'SELECT DATE(data_hora) AS dia, COUNT(*) AS pedidos,
                    COALESCE(SUM(valor_total), 0) AS total
             FROM pedido
             WHERE data_hora >= :de AND data_hora < :ate AND status <> :cancelado
             GROUP BY DATE(data_hora)
             ORDER BY dia',
            ['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]
        )->fetchAll();
    }

    public static function produtosMaisVendidos(string $de, string $ate, int $limite): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT pr.id_produto, pr.nome,
                    SUM(ip.quantidade) AS quantidade,
                    SUM(ip.quantidade * ip.preco_unitario) AS receita
             FROM item_pedido ip
             INNER JOIN pedido p   ON p.id_pedido = ip.id_pedido
             INNER JOIN produto pr ON pr.id_produto = ip.id_produto
             WHERE p.data_hora >= :de AND p.data_hora < :ate AND p.status <> :cancelado
             GROUP BY pr.id_produto, pr.nome
             ORDER BY quantidade DESC, receita DESC, pr.nome
             LIMIT :limite'
        );
        $stmt->bindValue(':de', $de);
        $stmt->bindValue(':ate', $ate);
        $stmt->bindValue(':cancelado', PedidoStatus::CANCELADO);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT); // LIMIT exige inteiro
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Consumo TEÓRICO de insumos: itens vendidos x ficha técnica (produto_insumo).
     * Independe de a baixa automática de estoque (Pedro/Fernanda) estar pronta.
     */
    public static function consumoInsumos(string $de, string $ate): array
    {
        return self::executar(
            'SELECT i.id_insumo, i.nome, i.unidade_medida,
                    SUM(ip.quantidade * pin.quantidade_utilizada) AS consumo,
                    SUM(ip.quantidade * pin.quantidade_utilizada * i.custo_unitario) AS custo
             FROM item_pedido ip
             INNER JOIN pedido p          ON p.id_pedido = ip.id_pedido
             INNER JOIN produto_insumo pin ON pin.id_produto = ip.id_produto
             INNER JOIN insumo i          ON i.id_insumo = pin.id_insumo
             WHERE p.data_hora >= :de AND p.data_hora < :ate AND p.status <> :cancelado
             GROUP BY i.id_insumo, i.nome, i.unidade_medida
             ORDER BY consumo DESC, i.nome',
            ['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]
        )->fetchAll();
    }

    public static function vendasPorCanal(string $de, string $ate): array
    {
        return self::executar(
            'SELECT canal, COUNT(*) AS pedidos, COALESCE(SUM(valor_total), 0) AS total
             FROM pedido
             WHERE data_hora >= :de AND data_hora < :ate AND status <> :cancelado
             GROUP BY canal
             ORDER BY total DESC',
            ['de' => $de, 'ate' => $ate, 'cancelado' => PedidoStatus::CANCELADO]
        )->fetchAll();
    }

    private static function executar(string $sql, array $params): PDOStatement
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
