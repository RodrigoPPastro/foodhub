<?php

require_once __DIR__ . '/../../config/database.php';

/** RF01/RF13/RF14 — PRODUTO e ficha técnica (PRODUTO_INSUMO). */
class ProdutoRepository
{
    /** Custo do prato = Σ (quantidade da ficha × custo do insumo). */
    private const SQL_CUSTO =
        '(SELECT COALESCE(SUM(pi.quantidade_utilizada * i.custo_unitario), 0)
          FROM produto_insumo pi INNER JOIN insumo i ON i.id_insumo = pi.id_insumo
          WHERE pi.id_produto = p.id_produto)';

    public static function listar(array $filtros = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filtros['id_categoria'])) {
            $where[] = 'p.id_categoria = :cat';
            $params['cat'] = (int) $filtros['id_categoria'];
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== null) {
            $where[] = 'p.ativo = :ativo';
            $params['ativo'] = (int) $filtros['ativo'];
        }
        if (!empty($filtros['busca'])) {
            $where[] = '(p.nome LIKE :busca OR p.descricao LIKE :busca2)';
            $like = '%' . addcslashes($filtros['busca'], '%_\\') . '%';
            $params['busca'] = $like;
            $params['busca2'] = $like;
        }
        $sql = 'SELECT p.id_produto, p.id_categoria, c.nome AS categoria, p.nome, p.descricao, p.foto,
                       p.preco_venda, p.ativo, p.setor_preparo, ' . self::SQL_CUSTO . ' AS custo
                FROM produto p INNER JOIN categoria c ON c.id_categoria = p.id_categoria'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.nome, p.nome';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function buscar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.id_produto, p.id_categoria, c.nome AS categoria, p.nome, p.descricao, p.foto,
                    p.preco_venda, p.ativo, p.setor_preparo, ' . self::SQL_CUSTO . ' AS custo
             FROM produto p INNER JOIN categoria c ON c.id_categoria = p.id_categoria WHERE p.id_produto = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Produtos vendáveis (ativos), indexados por id — usado ao montar pedidos. */
    public static function buscarAtivosPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT id_produto, nome, preco_venda, setor_preparo FROM produto WHERE ativo = 1 AND id_produto IN ($marc)"
        );
        $stmt->execute($ids);
        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[(int) $l['id_produto']] = $l;
        }
        return $mapa;
    }

    public static function buscarAtivoPorNome(string $nome): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id_produto, nome, preco_venda FROM produto WHERE ativo = 1 AND LOWER(nome) = LOWER(:n) LIMIT 1'
        );
        $stmt->execute(['n' => $nome]);
        return $stmt->fetch() ?: null;
    }

    public static function inserir(array $d): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO produto (id_categoria, nome, descricao, preco_venda, ativo, setor_preparo)
             VALUES (:cat, :nome, :descricao, :preco, :ativo, :setor)'
        )->execute(['cat' => $d['id_categoria'], 'nome' => $d['nome'], 'descricao' => $d['descricao'], 'preco' => $d['preco_venda'],
                    'ativo' => $d['ativo'], 'setor' => $d['setor_preparo']]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, array $d): void
    {
        Database::getConnection()->prepare(
            'UPDATE produto SET id_categoria = :cat, nome = :nome, descricao = :descricao, preco_venda = :preco, ativo = :ativo,
                               setor_preparo = :setor WHERE id_produto = :id'
        )->execute(['cat' => $d['id_categoria'], 'nome' => $d['nome'], 'descricao' => $d['descricao'], 'preco' => $d['preco_venda'],
                    'ativo' => $d['ativo'], 'setor' => $d['setor_preparo'], 'id' => $id]);
    }

    public static function definirAtivo(int $id, bool $ativo): void
    {
        Database::getConnection()->prepare('UPDATE produto SET ativo = :a WHERE id_produto = :id')
            ->execute(['a' => $ativo ? 1 : 0, 'id' => $id]);
    }

    public static function definirFoto(int $id, ?string $caminho): void
    {
        Database::getConnection()->prepare('UPDATE produto SET foto = :f WHERE id_produto = :id')
            ->execute(['f' => $caminho, 'id' => $id]);
    }

    public static function temVendas(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM item_pedido WHERE id_produto = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function excluir(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM produto WHERE id_produto = :id')->execute(['id' => $id]);
    }

    public static function ficha(int $idProduto): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT pi.id_insumo, i.nome, i.unidade_medida, pi.quantidade_utilizada, i.custo_unitario
             FROM produto_insumo pi INNER JOIN insumo i ON i.id_insumo = pi.id_insumo
             WHERE pi.id_produto = :id ORDER BY i.nome'
        );
        $stmt->execute(['id' => $idProduto]);
        return $stmt->fetchAll();
    }

    /** Substitui a ficha inteira (chamar dentro de transação). */
    public static function substituirFicha(int $idProduto, array $itens): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM produto_insumo WHERE id_produto = :id')->execute(['id' => $idProduto]);
        $ins = $pdo->prepare('INSERT INTO produto_insumo (id_produto, id_insumo, quantidade_utilizada) VALUES (:p, :i, :q)');
        foreach ($itens as $item) {
            $ins->execute(['p' => $idProduto, 'i' => $item['id_insumo'], 'q' => $item['quantidade']]);
        }
    }
}
