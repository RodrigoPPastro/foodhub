<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Transacao.php';

/** Contas a pagar / a receber (tabela conta_financeira). Só SQL — as regras ficam no ContaService. */
class ContaRepository
{
    /**
     * @param array{tipo:string,situacao?:string,de?:string,ate?:string,busca?:string} $f
     * situacao: aberta | vencida | baixada | cancelada | todas
     * "vencida" = aberta com vencimento antes de $hoje.
     */
    public static function listar(array $f, string $hoje): array
    {
        $where = ['c.tipo = :tipo'];
        $p = ['tipo' => $f['tipo']];
        switch ($f['situacao'] ?? 'todas') {
            case 'aberta':    $where[] = "c.status = 'aberta'"; break;
            case 'vencida':   $where[] = "c.status = 'aberta' AND c.data_vencimento < :hoje"; $p['hoje'] = $hoje; break;
            case 'baixada':   $where[] = "c.status = 'baixada'"; break;
            case 'cancelada': $where[] = "c.status = 'cancelada'"; break;
        }
        // Período filtra pela data que importa para cada situação: baixa (se baixada) ou vencimento.
        $campo = ($f['situacao'] ?? '') === 'baixada' ? 'c.data_baixa' : 'c.data_vencimento';
        if (!empty($f['de']))  { $where[] = "$campo >= :de";  $p['de'] = $f['de']; }
        if (!empty($f['ate'])) { $where[] = "$campo <= :ate"; $p['ate'] = $f['ate']; }
        if (!empty($f['busca'])) {
            $where[] = '(c.descricao LIKE :b1 OR c.pessoa LIKE :b2)';
            $p['b1'] = $p['b2'] = '%' . $f['busca'] . '%';
        }
        $ordem = ($f['situacao'] ?? '') === 'baixada' ? 'c.data_baixa DESC, c.id_conta DESC' : 'c.data_vencimento ASC, c.id_conta ASC';
        $stmt = Database::getConnection()->prepare(
            'SELECT c.* FROM conta_financeira c WHERE ' . implode(' AND ', $where) . " ORDER BY $ordem LIMIT 500"
        );
        $stmt->execute($p);
        return $stmt->fetchAll();
    }

    public static function buscar(int $id, bool $travar = false): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM conta_financeira WHERE id_conta = :id' . ($travar ? Transacao::travar() : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function existeParaPedido(int $idPedido): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM conta_financeira WHERE id_pedido = :p');
        $stmt->execute(['p' => $idPedido]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function inserir(array $d): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO conta_financeira (tipo, descricao, categoria, pessoa, valor, valor_bruto, comissao_percentual,
                                           data_emissao, data_vencimento, id_pedido, observacao)
             VALUES (:tipo, :descricao, :categoria, :pessoa, :valor, :valor_bruto, :comissao, :emissao, :vencimento, :id_pedido, :obs)'
        )->execute([
            'tipo' => $d['tipo'], 'descricao' => $d['descricao'], 'categoria' => $d['categoria'], 'pessoa' => $d['pessoa'] ?? null,
            'valor' => $d['valor'], 'valor_bruto' => $d['valor_bruto'] ?? null, 'comissao' => $d['comissao_percentual'] ?? null,
            'emissao' => $d['data_emissao'], 'vencimento' => $d['data_vencimento'],
            'id_pedido' => $d['id_pedido'] ?? null, 'obs' => $d['observacao'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, array $d): void
    {
        Database::getConnection()->prepare(
            'UPDATE conta_financeira SET descricao = :descricao, categoria = :categoria, pessoa = :pessoa, valor = :valor,
                    data_vencimento = :vencimento, observacao = :obs WHERE id_conta = :id'
        )->execute([
            'descricao' => $d['descricao'], 'categoria' => $d['categoria'], 'pessoa' => $d['pessoa'] ?? null,
            'valor' => $d['valor'], 'vencimento' => $d['data_vencimento'], 'obs' => $d['observacao'] ?? null, 'id' => $id,
        ]);
    }

    public static function baixar(int $id, string $dataBaixa, string $valor, ?int $idUsuario): void
    {
        Database::getConnection()->prepare(
            "UPDATE conta_financeira SET status = 'baixada', data_baixa = :d, valor = :v, id_usuario_baixa = :u WHERE id_conta = :id"
        )->execute(['d' => $dataBaixa, 'v' => $valor, 'u' => $idUsuario, 'id' => $id]);
    }

    public static function reabrir(int $id): void
    {
        Database::getConnection()->prepare(
            "UPDATE conta_financeira SET status = 'aberta', data_baixa = NULL, id_usuario_baixa = NULL WHERE id_conta = :id"
        )->execute(['id' => $id]);
    }

    public static function definirStatus(int $id, string $status): void
    {
        Database::getConnection()->prepare('UPDATE conta_financeira SET status = :s WHERE id_conta = :id')->execute(['s' => $status, 'id' => $id]);
    }

    /** Pedidos de plataformas externas (não cancelados) que ainda não têm repasse gerado. */
    public static function pedidosExternosSemRepasse(): array
    {
        return Database::getConnection()->query(
            "SELECT p.id_pedido FROM pedido p INNER JOIN canal_venda cv ON cv.codigo = p.canal
             WHERE cv.externo = 1 AND p.status <> 'cancelado'
               AND NOT EXISTS (SELECT 1 FROM conta_financeira c WHERE c.id_pedido = p.id_pedido)"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array{aberto:string,vencido:string,qtd_vencidas:int,baixado:string} totais de um tipo no período */
    public static function totais(string $tipo, string $hoje, ?string $de, ?string $ate): array
    {
        $per = '';
        $p = ['tipo' => $tipo, 'hoje1' => $hoje, 'hoje2' => $hoje];
        if ($de)  { $per .= ' AND data_vencimento >= :de';  $p['de'] = $de; }
        if ($ate) { $per .= ' AND data_vencimento <= :ate'; $p['ate'] = $ate; }
        $stmt = Database::getConnection()->prepare(
            "SELECT COALESCE(SUM(CASE WHEN status = 'aberta' THEN valor END), 0) AS aberto,
                    COALESCE(SUM(CASE WHEN status = 'aberta' AND data_vencimento < :hoje1 THEN valor END), 0) AS vencido,
                    COALESCE(SUM(CASE WHEN status = 'aberta' AND data_vencimento < :hoje2 THEN 1 END), 0) AS qtd_vencidas
             FROM conta_financeira WHERE tipo = :tipo $per"
        );
        $stmt->execute($p);
        return $stmt->fetch();
    }

    /** Total baixado (pago/recebido) de um tipo, pela DATA DA BAIXA dentro do período. */
    public static function totalBaixado(string $tipo, string $de, string $ate): string
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM conta_financeira WHERE tipo = :t AND status = 'baixada' AND data_baixa >= :de AND data_baixa <= :ate"
        );
        $stmt->execute(['t' => $tipo, 'de' => $de, 'ate' => $ate]);
        return (string) $stmt->fetchColumn();
    }

    /** Vendas já recebidas no caixa (pagamentos de pedidos NÃO vindos de plataforma), pela data do pagamento. */
    public static function vendasDiretasRecebidas(string $de, string $ate): string
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COALESCE(SUM(pg.valor), 0)
             FROM pagamento pg INNER JOIN pedido p ON p.id_pedido = pg.id_pedido
             INNER JOIN canal_venda cv ON cv.codigo = p.canal
             WHERE cv.externo = 0 AND p.status <> 'cancelado' AND pg.data_hora >= :de AND pg.data_hora < :ate"
        );
        $stmt->execute(['de' => $de . ' 00:00:00', 'ate' => (new DateTimeImmutable($ate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00']);
        return (string) $stmt->fetchColumn();
    }

    public static function canais(): array
    {
        return Database::getConnection()->query(
            'SELECT codigo, rotulo, comissao_percentual, prazo_repasse_dias FROM canal_venda WHERE externo = 1 AND ativo = 1 ORDER BY rotulo'
        )->fetchAll();
    }

    public static function salvarCanal(string $codigo, string $comissao, int $prazo): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE canal_venda SET comissao_percentual = :c, prazo_repasse_dias = :p WHERE codigo = :cod AND externo = 1'
        );
        $stmt->execute(['c' => $comissao, 'p' => $prazo, 'cod' => $codigo]);
        return $stmt->rowCount();
    }

    public static function buscarCanal(string $codigo): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT codigo, rotulo, comissao_percentual, prazo_repasse_dias FROM canal_venda WHERE codigo = :c');
        $stmt->execute(['c' => $codigo]);
        return $stmt->fetch() ?: null;
    }
}
