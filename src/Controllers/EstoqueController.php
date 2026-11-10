<?php

require_once __DIR__ . '/../../config/database.php';

class EstoqueController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Lista os produtos para selecionar na ficha técnica.
    public function listarProdutos(): array
    {
        $sql = "SELECT id_produto, nome, preco_venda
                FROM produto
                ORDER BY nome";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista os insumos ativos.
    public function listarAtivos(): array
    {
        $sql = "SELECT
                    id_insumo,
                    nome,
                    unidade_medida,
                    estoque_atual,
                    estoque_minimo,
                    custo_unitario
                FROM insumo
                WHERE ativo = 1
                ORDER BY nome";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista os ingredientes utilizados na ficha de um produto.
    public function listarFicha(int $idProduto): array
    {
        $sql = "SELECT
                    i.id_insumo,
                    i.nome,
                    i.unidade_medida,
                    i.custo_unitario,
                    pi.quantidade_utilizada,
                    (pi.quantidade_utilizada * i.custo_unitario) AS custo_item
                FROM produto_insumo pi
                INNER JOIN insumo i
                    ON i.id_insumo = pi.id_insumo
                WHERE pi.id_produto = :id_produto
                ORDER BY i.nome";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id_produto' => $idProduto
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calcula o custo total dos ingredientes do produto.
    public function custoFicha(int $idProduto): float
    {
        $sql = "SELECT
                    COALESCE(
                        SUM(pi.quantidade_utilizada * i.custo_unitario),
                        0
                    ) AS custo_total
                FROM produto_insumo pi
                INNER JOIN insumo i
                    ON i.id_insumo = pi.id_insumo
                WHERE pi.id_produto = :id_produto";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id_produto' => $idProduto
        ]);

        return (float) $stmt->fetchColumn();
    }

    // Lista insumos que atingiram ou estão abaixo do estoque mínimo.
    public function listarEstoqueBaixo(): array
    {
        $sql = "SELECT
                    id_insumo,
                    nome,
                    unidade_medida,
                    estoque_atual,
                    estoque_minimo
                FROM insumo
                WHERE ativo = 1
                  AND estoque_atual <= estoque_minimo
                ORDER BY nome";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}