<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';
require_once __DIR__ . '/../Models/SetorPreparo.php';
require_once __DIR__ . '/Transacao.php';

/** RF17–RF21 — PEDIDO e ITEM_PEDIDO. */
class PedidoRepository
{
    private const SELECT_BASE =
        'SELECT p.*, m.numero AS mesa_numero, e.nome AS entregador_nome,
                u.nome_completo AS usuario_nome, u.telefone_celular AS usuario_telefone, g.nome AS garcom_nome,
                COALESCE((SELECT SUM(pg.valor) FROM pagamento pg WHERE pg.id_pedido = p.id_pedido), 0) AS valor_pago
         FROM pedido p
         LEFT JOIN mesa m ON m.id_mesa = p.id_mesa
         LEFT JOIN entregador e ON e.id_entregador = p.id_entregador
         LEFT JOIN usuario u ON u.id_usuario = p.id_usuario_cliente
         LEFT JOIN garcom g ON g.id_garcom = p.id_garcom';

    public static function inserir(array $d): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO pedido (id_usuario_cliente, id_mesa, id_garcom, data_hora, canal, status, plataforma_externa, id_pedido_externo,
                                 valor_total, nome_cliente, telefone_cliente, endereco_entrega, observacao)
             VALUES (:cliente, :mesa, :garcom, :data_hora, :canal, :status, :plataforma, :externo, :total, :nome, :telefone, :endereco, :obs)'
        )->execute([
            'cliente' => $d['id_usuario_cliente'], 'mesa' => $d['id_mesa'], 'garcom' => $d['id_garcom'] ?? null, 'data_hora' => $d['data_hora'],
            'canal' => $d['canal'], 'status' => PedidoStatus::NOVO,
            'plataforma' => $d['plataforma_externa'], 'externo' => $d['id_pedido_externo'],
            'total' => $d['valor_total'], 'nome' => $d['nome_cliente'], 'telefone' => $d['telefone_cliente'],
            'endereco' => $d['endereco_entrega'], 'obs' => $d['observacao'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function inserirItens(int $idPedido, array $itens): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, observacao, setor_preparo, status_item)
             VALUES (:p, :prod, :q, :preco, :obs, :setor, :st)'
        );
        foreach ($itens as $i) {
            $stmt->execute(['p' => $idPedido, 'prod' => $i['id_produto'], 'q' => $i['quantidade'], 'preco' => $i['preco_unitario'], 'obs' => $i['observacao'],
                            'setor' => $i['setor_preparo'] ?? SetorPreparo::COZINHA, 'st' => $i['status_item'] ?? SetorPreparo::ITEM_AGUARDANDO]);
        }
    }

    /** Linha crua do pedido (com trava de linha opcional, para transições de status). */
    public static function buscarBruto(int $id, bool $travar = false): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM pedido WHERE id_pedido = :id' . ($travar ? Transacao::travar() : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Pedido com mesa, entregador, cliente e valor já pago. */
    public static function buscar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT_BASE . ' WHERE p.id_pedido = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array[]> itens agrupados por id do pedido */
    public static function itens(array $idsPedido): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsPedido)));
        if ($ids === []) {
            return [];
        }
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT ip.id_item, ip.id_pedido, ip.id_produto, pr.nome, ip.quantidade, ip.preco_unitario, ip.observacao,
                    ip.setor_preparo, ip.status_item, ip.data_hora_inicio_preparo
             FROM item_pedido ip INNER JOIN produto pr ON pr.id_produto = ip.id_produto
             WHERE ip.id_pedido IN ($marc) ORDER BY ip.id_item"
        );
        $stmt->execute($ids);
        $porPedido = [];
        foreach ($stmt->fetchAll() as $l) {
            $porPedido[(int) $l['id_pedido']][] = $l;
        }
        return $porPedido;
    }

    /** Quadro: todos os em aberto (de qualquer dia) + entregues/cancelados a partir de $desde. */
    public static function listarQuadro(string $desde, ?string $canal = null): array
    {
        $params = ['desde' => $desde];
        $marc = [];
        foreach (PedidoStatus::EM_ABERTO as $i => $s) {
            $marc[] = ':s' . $i;
            $params['s' . $i] = $s;
        }
        $sql = self::SELECT_BASE . ' WHERE (p.status IN (' . implode(',', $marc) . ') OR p.data_hora >= :desde)';
        if ($canal !== null && $canal !== '') {
            $sql .= ' AND p.canal = :canal';
            $params['canal'] = $canal;
        }
        $stmt = Database::getConnection()->prepare($sql . ' ORDER BY p.data_hora DESC, p.id_pedido DESC LIMIT 400');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function listarPorStatus(array $status): array
    {
        $marc = implode(',', array_fill(0, count($status), '?'));
        $stmt = Database::getConnection()->prepare(self::SELECT_BASE . " WHERE p.status IN ($marc) ORDER BY p.data_hora ASC, p.id_pedido ASC");
        $stmt->execute(array_values($status));
        return $stmt->fetchAll();
    }

    /**
     * Histórico: pedidos finalizados (entregues e cancelados) no período, mais recentes primeiro.
     * $busca: número do pedido / da mesa, nome do cliente ou código da plataforma.
     */
    public static function listarHistorico(string $de, string $ate, ?string $canal, string $busca, int $limite = 500): array
    {
        $sql = self::SELECT_BASE . ' WHERE p.status IN (:e, :c) AND p.data_hora >= :de AND p.data_hora < :ate';
        $params = ['e' => PedidoStatus::ENTREGUE, 'c' => PedidoStatus::CANCELADO, 'de' => $de, 'ate' => $ate];
        if ($canal !== null && $canal !== '') {
            $sql .= ' AND p.canal = :canal';
            $params['canal'] = $canal;
        }
        if ($busca !== '') {
            $num = ltrim($busca, '#');
            if (ctype_digit($num)) {
                $sql .= ' AND (p.id_pedido = :n1 OR m.numero = :n2)';
                $params['n1'] = (int) $num;
                $params['n2'] = (int) $num;
            } else {
                $like = '%' . addcslashes($busca, '%_\\') . '%';
                $sql .= ' AND (p.nome_cliente LIKE :b1 OR u.nome_completo LIKE :b2 OR p.id_pedido_externo LIKE :b3)';
                $params += ['b1' => $like, 'b2' => $like, 'b3' => $like];
            }
        }
        $stmt = Database::getConnection()->prepare($sql . ' ORDER BY p.data_hora DESC, p.id_pedido DESC LIMIT ' . max(1, min($limite, 1000)));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<int,array[]> pagamentos agrupados por pedido */
    public static function pagamentosDosPedidos(array $idsPedido): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsPedido)));
        if ($ids === []) {
            return [];
        }
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT pg.id_pedido, f.descricao AS forma, pg.valor FROM pagamento pg
             INNER JOIN forma_pagamento f ON f.id_forma_pagamento = pg.id_forma_pagamento
             WHERE pg.id_pedido IN ($marc) ORDER BY pg.data_hora, pg.id_pagamento"
        );
        $stmt->execute($ids);
        $porPedido = [];
        foreach ($stmt->fetchAll() as $l) {
            $porPedido[(int) $l['id_pedido']][] = ['forma' => $l['forma'], 'valor' => (float) $l['valor']];
        }
        return $porPedido;
    }

    public static function listarPorCliente(int $idUsuario, int $limite = 30): array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT_BASE . ' WHERE p.id_usuario_cliente = :u ORDER BY p.data_hora DESC, p.id_pedido DESC LIMIT :limite');
        $stmt->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listarRecentes(int $limite = 8): array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT_BASE . ' ORDER BY p.data_hora DESC, p.id_pedido DESC LIMIT :limite');
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Pedidos de uma mesa que ainda a mantêm ocupada (não cancelados, e não "entregue + quitado"). */
    public static function abertosDaMesa(int $idMesa): array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT_BASE . ' WHERE p.id_mesa = :m AND p.status <> :c ORDER BY p.data_hora, p.id_pedido');
        $stmt->execute(['m' => $idMesa, 'c' => PedidoStatus::CANCELADO]);
        return array_values(array_filter($stmt->fetchAll(), function (array $p) {
            $saldo = round((float) $p['valor_total'] - (float) $p['valor_pago'], 2);
            return $p['status'] !== PedidoStatus::ENTREGUE || $saldo > 0;
        }));
    }

    /** Grava a taxa de serviço (valores em centavos) para repasse ao garçom. */
    public static function registrarTaxaServico(int $idMesa, ?int $idUsuario, ?int $idGarcom, int $baseCent, int $percentual, int $taxaCent): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO taxa_servico (id_mesa, id_usuario, id_garcom, base, percentual, valor) VALUES (:m, :u, :g, :b, :p, :v)'
        )->execute(['m' => $idMesa, 'u' => $idUsuario, 'g' => $idGarcom, 'b' => $baseCent / 100, 'p' => $percentual, 'v' => $taxaCent / 100]);
    }

    public static function atualizarStatus(int $id, string $status, ?string $inicioPreparo = null): void
    {
        $pdo = Database::getConnection();
        if ($inicioPreparo !== null) {
            $pdo->prepare('UPDATE pedido SET status = :s, data_hora_inicio_preparo = :i WHERE id_pedido = :id')
                ->execute(['s' => $status, 'i' => $inicioPreparo, 'id' => $id]);
            return;
        }
        $pdo->prepare('UPDATE pedido SET status = :s WHERE id_pedido = :id')->execute(['s' => $status, 'id' => $id]);
    }

    /* ── Andamento dos itens por setor (cozinha / bar) ─────────────────── */

    /** Setor começa a preparar: itens "aguardando" do setor → "em preparo". @return int itens afetados */
    public static function iniciarItensDoSetor(int $idPedido, string $setor, string $agora): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE item_pedido SET status_item = :novo, data_hora_inicio_preparo = :agora
             WHERE id_pedido = :p AND setor_preparo = :s AND status_item = :atual'
        );
        $stmt->execute(['novo' => SetorPreparo::ITEM_EM_PREPARO, 'agora' => $agora, 'p' => $idPedido, 's' => $setor, 'atual' => SetorPreparo::ITEM_AGUARDANDO]);
        return $stmt->rowCount();
    }

    /** Setor terminou: itens "em preparo" do setor → "pronto". @return int itens afetados */
    public static function concluirItensDoSetor(int $idPedido, string $setor): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE item_pedido SET status_item = :novo WHERE id_pedido = :p AND setor_preparo = :s AND status_item = :atual'
        );
        $stmt->execute(['novo' => SetorPreparo::ITEM_PRONTO, 'p' => $idPedido, 's' => $setor, 'atual' => SetorPreparo::ITEM_EM_PREPARO]);
        return $stmt->rowCount();
    }

    /** Itens com preparo (cozinha/bar) que ainda não ficaram prontos. */
    public static function contarPreparoPendente(int $idPedido): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM item_pedido WHERE id_pedido = :p AND setor_preparo IN (:c, :b) AND status_item IN (:ag, :ep)'
        );
        $stmt->execute(['p' => $idPedido, 'c' => SetorPreparo::COZINHA, 'b' => SetorPreparo::BAR,
                        'ag' => SetorPreparo::ITEM_AGUARDANDO, 'ep' => SetorPreparo::ITEM_EM_PREPARO]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quando o pedido inteiro muda de status pelo quadro de Pedidos (ou no fechamento da mesa),
     * os itens acompanham — assim as telas da cozinha e do bar nunca ficam com sobras.
     */
    public static function alinharItens(int $idPedido, string $statusPedido, string $agora): void
    {
        $pdo = Database::getConnection();
        $comPreparo = "setor_preparo IN ('" . SetorPreparo::COZINHA . "','" . SetorPreparo::BAR . "')";
        switch ($statusPedido) {
            case PedidoStatus::EM_PREPARO:
                $pdo->prepare("UPDATE item_pedido SET status_item = :novo, data_hora_inicio_preparo = :agora
                               WHERE id_pedido = :p AND $comPreparo AND status_item = :atual")
                    ->execute(['novo' => SetorPreparo::ITEM_EM_PREPARO, 'agora' => $agora, 'p' => $idPedido, 'atual' => SetorPreparo::ITEM_AGUARDANDO]);
                break;
            case PedidoStatus::PRONTO:
            case PedidoStatus::SAIU:
                $pdo->prepare("UPDATE item_pedido SET status_item = :novo
                               WHERE id_pedido = :p AND $comPreparo AND status_item IN (:a, :b)")
                    ->execute(['novo' => SetorPreparo::ITEM_PRONTO, 'p' => $idPedido, 'a' => SetorPreparo::ITEM_AGUARDANDO, 'b' => SetorPreparo::ITEM_EM_PREPARO]);
                break;
            case PedidoStatus::ENTREGUE:
                $pdo->prepare('UPDATE item_pedido SET status_item = :novo WHERE id_pedido = :p')
                    ->execute(['novo' => SetorPreparo::ITEM_ENTREGUE, 'p' => $idPedido]);
                break;
        }
    }

    public static function definirEntregador(int $id, ?int $idEntregador): void
    {
        Database::getConnection()->prepare('UPDATE pedido SET id_entregador = :e WHERE id_pedido = :id')->execute(['e' => $idEntregador, 'id' => $id]);
    }

    public static function definirEstoqueBaixado(int $id, bool $baixado): void
    {
        Database::getConnection()->prepare('UPDATE pedido SET estoque_baixado = :b WHERE id_pedido = :id')->execute(['b' => $baixado ? 1 : 0, 'id' => $id]);
    }

    public static function definirSincronizado(int $id, bool $ok): void
    {
        Database::getConnection()->prepare('UPDATE pedido SET sincronizado_status_externo = :s WHERE id_pedido = :id')->execute(['s' => $ok ? 1 : 0, 'id' => $id]);
    }

    public static function buscarExterno(string $plataforma, string $idExterno): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM pedido WHERE plataforma_externa = :p AND id_pedido_externo = :e LIMIT 1');
        $stmt->execute(['p' => $plataforma, 'e' => $idExterno]);
        return $stmt->fetch() ?: null;
    }

    public static function contarEntregasEmRota(int $idEntregador, int $ignorarPedido = 0): int
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM pedido WHERE id_entregador = :e AND status = :s AND id_pedido <> :p');
        $stmt->execute(['e' => $idEntregador, 's' => PedidoStatus::SAIU, 'p' => $ignorarPedido]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,int> contagem por status (todos os status presentes, com 0) */
    public static function contarPorStatusNoDia(string $de, string $ate): array
    {
        $stmt = Database::getConnection()->prepare('SELECT status, COUNT(*) AS total FROM pedido WHERE data_hora >= :de AND data_hora < :ate GROUP BY status');
        $stmt->execute(['de' => $de, 'ate' => $ate]);
        $mapa = array_fill_keys(PedidoStatus::TODOS, 0);
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['status']] = (int) $l['total'];
        }
        return $mapa;
    }
}
