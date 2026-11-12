<?php

require_once __DIR__ . '/../Repositories/PedidoRepository.php';
require_once __DIR__ . '/../Repositories/GorjetaRepository.php';
require_once __DIR__ . '/../Repositories/ProdutoRepository.php';
require_once __DIR__ . '/../Repositories/MesaRepository.php';
require_once __DIR__ . '/../Repositories/EntregadorRepository.php';
require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/../Repositories/NotificacaoRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';
require_once __DIR__ . '/../Models/CanalVenda.php';
require_once __DIR__ . '/../Models/FormaPagamento.php';
require_once __DIR__ . '/../Models/SetorPreparo.php';
require_once __DIR__ . '/../Adapters/Adapters.php';
require_once __DIR__ . '/EstoqueService.php';
require_once __DIR__ . '/ContaService.php';
require_once __DIR__ . '/NotificacaoService.php';
require_once __DIR__ . '/MesaService.php';
require_once __DIR__ . '/PagamentoService.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/Numero.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * RF17–RF21 — pedidos: criação (balcão, mesa, delivery próprio, plataformas externas),
 * fluxo de status do kanban, baixa/estorno de estoque, entregadores, atrasos e notificações.
 */
class PedidoService
{
    private const MAX_ITENS = 50;
    private const MAX_QTD   = 99;

    /* ══════════════ Consultas ══════════════ */

    /** Quadro kanban (RF17): em aberto + entregues/cancelados de hoje. */
    public static function quadro(array $get): array
    {
        $hoje = PeriodoService::dia(null);
        $canal = trim((string) ($get['canal'] ?? ''));
        $linhas = PedidoRepository::listarQuadro($hoje['de'], $canal !== '' ? $canal : null);
        self::notificarAtrasos($linhas);

        $colunas = array_fill_keys(PedidoStatus::TODOS, []);
        foreach (self::formatarLista($linhas) as $p) {
            $colunas[$p['status']][] = $p;
        }
        return [
            'colunas'        => $colunas,
            'canais'         => CanalVenda::ativos(),
            'atraso_minutos' => self::atrasoMinutos(),
            'gerado_em'      => PeriodoService::agora()->format('H:i:s'),
        ];
    }

    /**
     * Painel de preparo, mais antigos primeiro.
     *   $setor = null      → todos os pedidos novos/em preparo (visão geral, usada pelo dashboard)
     *   $setor = cozinha|bar → só os pedidos com itens daquele setor ainda por fazer, e só esses itens
     */
    public static function cozinha(?string $setor = null): array
    {
        if ($setor !== null && !in_array($setor, SetorPreparo::COM_PREPARO, true)) {
            throw new RegraNegocioException('Setor de preparo inválido.');
        }
        $linhas = PedidoRepository::listarPorStatus([PedidoStatus::NOVO, PedidoStatus::EM_PREPARO]);
        self::notificarAtrasos($linhas);
        $pedidos = self::formatarLista($linhas);
        if ($setor !== null) {
            $pedidos = self::ticketsDoSetor($pedidos, $setor);
        }
        return [
            'setor'          => $setor,
            'setor_rotulo'   => $setor !== null ? SetorPreparo::rotulo($setor) : null,
            'pedidos'        => $pedidos,
            'atraso_minutos' => self::atrasoMinutos(),
            'gerado_em'      => PeriodoService::agora()->format('H:i:s'),
        ];
    }

    /** Recorta cada pedido para a visão de um setor: itens dele, estado, cronômetro e o andamento dos outros setores. */
    private static function ticketsDoSetor(array $pedidos, string $setor): array
    {
        $limite = self::atrasoMinutos();
        $agora = PeriodoService::agora()->getTimestamp();
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $tickets = [];

        foreach ($pedidos as $p) {
            $meus = array_values(array_filter($p['itens'], fn($i) => $i['setor_preparo'] === $setor
                && in_array($i['status_item'], [SetorPreparo::ITEM_AGUARDANDO, SetorPreparo::ITEM_EM_PREPARO], true)));
            if ($meus === []) {
                continue;
            }
            $aguardando = (bool) array_filter($meus, fn($i) => $i['status_item'] === SetorPreparo::ITEM_AGUARDANDO);

            $minutos = 0;
            if (!$aguardando) {
                $inicios = array_filter(array_column($meus, 'inicio_preparo'));
                $inicio = $inicios ? min($inicios) : $p['inicio_preparo'];
                if ($inicio) {
                    $minutos = max(0, (int) floor(($agora - (new DateTimeImmutable($inicio, $fuso))->getTimestamp()) / 60));
                }
            }

            // Andamento dos outros setores, para quem monta a bandeja saber se falta algo
            $outros = [];
            foreach (SetorPreparo::COM_PREPARO as $s) {
                if ($s === $setor) {
                    continue;
                }
                $deles = array_column(array_filter($p['itens'], fn($i) => $i['setor_preparo'] === $s), 'status_item');
                if ($deles === []) {
                    continue;
                }
                $estado = in_array(SetorPreparo::ITEM_AGUARDANDO, $deles, true) ? SetorPreparo::ITEM_AGUARDANDO
                    : (in_array(SetorPreparo::ITEM_EM_PREPARO, $deles, true) ? SetorPreparo::ITEM_EM_PREPARO : SetorPreparo::ITEM_PRONTO);
                $outros[] = ['setor' => $s, 'setor_rotulo' => SetorPreparo::rotulo($s), 'estado' => $estado, 'estado_rotulo' => SetorPreparo::ROTULOS_ITEM[$estado]];
            }

            $tickets[] = [
                'itens'              => $meus,
                'estado_setor'       => $aguardando ? SetorPreparo::ITEM_AGUARDANDO : SetorPreparo::ITEM_EM_PREPARO,
                'minutos_em_preparo' => $minutos,
                'atrasado'           => !$aguardando && $minutos >= $limite,
                'outros_setores'     => $outros,
                'sem_preparo'        => array_values(array_filter($p['itens'], fn($i) => $i['setor_preparo'] === SetorPreparo::NENHUM)),
            ] + $p;
        }
        return $tickets;
    }

    /** Histórico de pedidos finalizados (entregues e cancelados) com filtros, pagamentos e totais. */
    public static function historico(array $get): array
    {
        $periodo = PeriodoService::intervalo($get['de'] ?? null, $get['ate'] ?? null, 1);
        $canal = trim((string) ($get['canal'] ?? ''));
        $busca = trim((string) ($get['busca'] ?? ''));
        if (mb_strlen($busca, 'UTF-8') > 60) {
            throw new RegraNegocioException('A busca pode ter no máximo 60 caracteres.');
        }
        $linhas = PedidoRepository::listarHistorico($periodo['de'], $periodo['ate'], $canal !== '' ? $canal : null, $busca);
        $pagamentos = PedidoRepository::pagamentosDosPedidos(array_column($linhas, 'id_pedido'));
        $pedidos = array_map(fn($p) => $p + ['pagamentos' => $pagamentos[$p['id_pedido']] ?? []], self::formatarLista($linhas));

        $validos = array_filter($pedidos, fn($p) => $p['status'] !== PedidoStatus::CANCELADO);
        $total = array_sum(array_map(fn($p) => Dinheiro::centavos($p['valor_total']), $validos));
        return [
            'inicio' => $periodo['inicio'], 'fim' => $periodo['fim'],
            'pedidos' => $pedidos,
            'canais' => CanalVenda::ativos(),
            'resumo' => [
                'quantidade' => count($pedidos), 'entregues' => count($validos), 'cancelados' => count($pedidos) - count($validos),
                'total' => Dinheiro::reais($total),
                'ticket_medio' => $validos ? Dinheiro::reais((int) round($total / count($validos))) : 0.0,
                'limite_atingido' => count($pedidos) >= 500,
            ],
        ];
    }

    public static function buscar(int $id): array
    {
        $p = PedidoRepository::buscar($id);
        if ($p === null) {
            throw new RegraNegocioException('Pedido não encontrado.');
        }
        return self::formatarLista([$p])[0];
    }

    /** RF19: o cliente acompanha somente os PRÓPRIOS pedidos. */
    public static function doCliente(int $idUsuario): array
    {
        return self::formatarLista(PedidoRepository::listarPorCliente($idUsuario));
    }

    public static function recentes(int $limite = 8): array
    {
        return self::formatarLista(PedidoRepository::listarRecentes(max(1, min($limite, 30))));
    }

    /* ══════════════ Criação ══════════════ */

    /**
     * Pedido lançado pelo Master (balcão, mesa, delivery próprio por telefone).
     * Plataformas externas só entram pelo webhook.
     */
    public static function criarManual(array $e, ?int $idUsuarioOperador): array
    {
        $canal = (string) ($e['canal'] ?? '');
        if (!array_key_exists($canal, CanalVenda::ativos())) {
            throw new RegraNegocioException('Selecione um canal de venda válido.');
        }
        if (CanalVenda::ehExterno($canal)) {
            throw new RegraNegocioException('Pedidos de plataformas externas chegam automaticamente pela integração.');
        }
        $pagamentos = is_array($e['pagamentos'] ?? null) ? $e['pagamentos'] : [];

        return self::criar([
            'canal' => $canal,
            'id_usuario_cliente' => null,
            'id_mesa' => $canal === 'mesa' ? (int) ($e['id_mesa'] ?? 0) : null,
            'id_garcom' => $canal === 'mesa' ? ((int) ($e['id_garcom'] ?? 0) ?: null) : null,
            'nome_cliente' => $e['nome_cliente'] ?? '',
            'telefone_cliente' => $e['telefone_cliente'] ?? '',
            'endereco_entrega' => $e['endereco_entrega'] ?? '',
            'observacao' => $e['observacao'] ?? '',
        ], $e['itens'] ?? [], $pagamentos);
    }

    /**
     * RF19 — Delivery Próprio: o cliente autenticado escolhe produtos, endereço e forma de pagamento.
     * O canal, o cliente e os preços são decididos AQUI (nunca confiamos no navegador).
     */
    public static function criarDoCliente(array $e, int $idUsuario): array
    {
        $usuario = UsuarioRepository::buscarPorId($idUsuario);
        if ($usuario === null) {
            throw new RegraNegocioException('Usuário não encontrado.');
        }
        $forma = strtolower(trim((string) ($e['forma_pagamento'] ?? '')));
        if (!in_array($forma, FormaPagamento::validas(), true)) {
            throw new RegraNegocioException('Escolha a forma de pagamento.');
        }
        $endereco = trim((string) ($e['endereco_entrega'] ?? ''));

        $pedido = self::criar([
            'canal' => 'delivery_proprio',
            'id_usuario_cliente' => $idUsuario,
            'id_mesa' => null,
            'nome_cliente' => $usuario['nome_completo'],
            'telefone_cliente' => $usuario['telefone_celular'],
            'endereco_entrega' => $endereco !== '' ? $endereco : $usuario['endereco_completo'],
            'observacao' => $e['observacao'] ?? '',
        ], $e['itens'] ?? [], [], $forma);

        return $pedido;
    }

    /**
     * RF20 — recebe um pedido no formato (aproximado) de uma plataforma de delivery.
     * Idempotente: reenvio do mesmo id externo devolve o pedido já gravado.
     *
     * @param array $p {plataforma, id_pedido_externo, cliente:{nome,telefone}, endereco, itens:[{id_produto|nome, quantidade}],
     *                  valor_total?, forma_pagamento?}
     */
    public static function receberExterno(array $p): array
    {
        $plataforma = strtolower(trim((string) ($p['plataforma'] ?? '')));
        $idExterno = trim((string) ($p['id_pedido_externo'] ?? ''));
        if (!preg_match('/^[a-z0-9_]{2,30}$/', $plataforma) || !CanalVenda::ehExterno($plataforma)
            || !array_key_exists($plataforma, CanalVenda::ativos())) {
            throw new RegraNegocioException('Plataforma desconhecida ou inativa.');
        }
        if ($idExterno === '' || mb_strlen($idExterno, 'UTF-8') > 60) {
            throw new RegraNegocioException('Informe o id_pedido_externo (máx. 60 caracteres).');
        }

        $existente = PedidoRepository::buscarExterno($plataforma, $idExterno);
        if ($existente !== null) {
            return self::buscar((int) $existente['id_pedido']) + ['duplicado' => true];
        }

        $cliente = is_array($p['cliente'] ?? null) ? $p['cliente'] : [];
        $itens = [];
        foreach ((is_array($p['itens'] ?? null) ? $p['itens'] : []) as $i) {
            $idProduto = (int) ($i['id_produto'] ?? 0);
            if ($idProduto <= 0 && !empty($i['nome'])) {
                $prod = ProdutoRepository::buscarAtivoPorNome(trim((string) $i['nome']));
                $idProduto = $prod ? (int) $prod['id_produto'] : 0;
            }
            $itens[] = ['id_produto' => $idProduto, 'quantidade' => $i['quantidade'] ?? 0, 'observacao' => $i['observacao'] ?? null];
        }

        $forma = strtolower(trim((string) ($p['forma_pagamento'] ?? 'pix')));
        if (!in_array($forma, FormaPagamento::validas(), true)) {
            $forma = 'pix';
        }

        $pedido = self::criar([
            'canal' => $plataforma,
            'id_usuario_cliente' => null,
            'id_mesa' => null,
            'plataforma_externa' => $plataforma,
            'id_pedido_externo' => $idExterno,
            'nome_cliente' => $cliente['nome'] ?? '',
            'telefone_cliente' => $cliente['telefone'] ?? '',
            'endereco_entrega' => $p['endereco'] ?? '',
            'observacao' => $p['observacao'] ?? '',
        ], $itens, [], $forma);

        // Contas a receber: o repasse da plataforma (total − comissão) entra previsto para o prazo do canal.
        // Se falhar, o pedido NÃO é perdido: a tela de contas gera o repasse que estiver faltando.
        try {
            ContaService::gerarRepasse((int) $pedido['id_pedido']);
        } catch (Throwable $e) {
            error_log('[FoodHub] repasse do pedido ' . $pedido['id_pedido'] . ': ' . $e->getMessage());
        }

        return $pedido + ['duplicado' => false];
    }

    /**
     * Núcleo da criação. Valida, congela preços, grava pedido + itens (+ pagamento) numa transação.
     * @param array $d       dados do pedido
     * @param array $itens   [{id_produto, quantidade, observacao?}]
     * @param array $pagamentos pagamentos informados no balcão
     * @param string|null $formaIntegral quando informada, quita 100% do pedido com esta forma
     */
    private static function criar(array $d, array $itens, array $pagamentos, ?string $formaIntegral = null): array
    {
        $canal = (string) $d['canal'];
        $presencial = CanalVenda::ehPresencial($canal);

        // ── itens
        if ($itens === [] || count($itens) > self::MAX_ITENS) {
            throw new RegraNegocioException('Adicione de 1 a ' . self::MAX_ITENS . ' itens ao pedido.');
        }
        $quantidades = [];
        $obs = [];
        foreach ($itens as $i) {
            $idProduto = (int) ($i['id_produto'] ?? 0);
            $qtd = filter_var($i['quantidade'] ?? null, FILTER_VALIDATE_INT);
            if ($idProduto <= 0) {
                throw new RegraNegocioException('Há um item que não corresponde a nenhum produto do cardápio.');
            }
            if ($qtd === false || $qtd < 1 || $qtd > self::MAX_QTD) {
                throw new RegraNegocioException('A quantidade de cada item deve estar entre 1 e ' . self::MAX_QTD . '.');
            }
            $quantidades[$idProduto] = ($quantidades[$idProduto] ?? 0) + $qtd;
            $texto = trim((string) ($i['observacao'] ?? ''));
            if (mb_strlen($texto, 'UTF-8') > 200) {
                throw new RegraNegocioException('A observação de um item pode ter no máximo 200 caracteres.');
            }
            if ($texto !== '') {
                $obs[$idProduto] = $texto;
            }
        }
        $produtos = ProdutoRepository::buscarAtivosPorIds(array_keys($quantidades));
        $linhas = [];
        $totalCentavos = 0;
        foreach ($quantidades as $idProduto => $qtd) {
            if (!isset($produtos[$idProduto])) {
                throw new RegraNegocioException('Um dos produtos não está mais disponível no cardápio. Atualize a página e tente novamente.');
            }
            $preco = Dinheiro::centavos($produtos[$idProduto]['preco_venda']);   // preço SEMPRE do banco
            $totalCentavos += $preco * $qtd;
            $setor = (string) ($produtos[$idProduto]['setor_preparo'] ?? SetorPreparo::COZINHA);
            $linhas[] = ['id_produto' => $idProduto, 'quantidade' => $qtd, 'preco_unitario' => Dinheiro::decimal($preco), 'observacao' => $obs[$idProduto] ?? null,
                         'setor_preparo' => $setor, 'status_item' => SetorPreparo::statusInicial($setor, $presencial)];
        }
        // Pedido só com itens de pronta entrega (água, lata…) não passa pela cozinha nem pelo bar
        $temPreparo = (bool) array_filter($linhas, fn($l) => SetorPreparo::temPreparo($l['setor_preparo']));

        // ── dados do cliente / entrega / mesa
        $nome = trim((string) ($d['nome_cliente'] ?? ''));
        $telefone = trim((string) ($d['telefone_cliente'] ?? ''));
        $endereco = trim((string) ($d['endereco_entrega'] ?? ''));
        $observacao = trim((string) ($d['observacao'] ?? ''));
        $idMesa = $d['id_mesa'] ?? null;
        // Garçom que atende a mesa (opcional): precisa existir e estar ativo
        $idGarcomPedido = ($d['canal'] ?? '') === 'mesa' && !empty($d['id_garcom']) ? (int) $d['id_garcom'] : null;
        if ($idGarcomPedido !== null) {
            $g = GorjetaRepository::buscarGarcom($idGarcomPedido);
            if ($g === null || !(int) $g['ativo']) {
                throw new RegraNegocioException('O garçom escolhido não existe ou está inativo.');
            }
        }

        if (mb_strlen($nome, 'UTF-8') > 80) {
            throw new RegraNegocioException('O nome do cliente pode ter no máximo 80 caracteres.');
        }
        if ($telefone !== '' && !preg_match('/^\(\+55\)\d{2}-\d{8,9}$/', $telefone)) {
            throw new RegraNegocioException('Telefone inválido. Use o formato (+55)XX-XXXXXXXX.');
        }
        if (mb_strlen($observacao, 'UTF-8') > 300) {
            throw new RegraNegocioException('A observação pode ter no máximo 300 caracteres.');
        }
        if ($canal === 'mesa') {
            $mesa = $idMesa ? MesaRepository::buscar((int) $idMesa) : null;
            if ($mesa === null) {
                throw new RegraNegocioException('Selecione a mesa do pedido.');
            }
        } else {
            $idMesa = null;
        }
        if (!$presencial) {
            if (mb_strlen($endereco, 'UTF-8') < 10 || mb_strlen($endereco, 'UTF-8') > 255) {
                throw new RegraNegocioException('Informe o endereço de entrega completo (rua, número e bairro).');
            }
            if ($telefone === '') {
                throw new RegraNegocioException('Informe um telefone de contato para a entrega.');
            }
        } else {
            $endereco = '';
        }

        $agora = PeriodoService::agora()->format('Y-m-d H:i:s');
        $idPedido = Transacao::executar(function () use ($d, $canal, $idMesa, $idGarcomPedido, $agora, $totalCentavos, $nome, $telefone, $endereco, $observacao, $linhas, $pagamentos, $formaIntegral, $temPreparo, $presencial) {
            $id = PedidoRepository::inserir([
                'id_usuario_cliente' => $d['id_usuario_cliente'] ?? null,
                'id_mesa' => $idMesa, 'id_garcom' => $idGarcomPedido, 'data_hora' => $agora, 'canal' => $canal,
                'plataforma_externa' => $d['plataforma_externa'] ?? null,
                'id_pedido_externo' => $d['id_pedido_externo'] ?? null,
                'valor_total' => Dinheiro::decimal($totalCentavos),
                'nome_cliente' => $nome !== '' ? $nome : null, 'telefone_cliente' => $telefone !== '' ? $telefone : null,
                'endereco_entrega' => $endereco !== '' ? $endereco : null, 'observacao' => $observacao !== '' ? $observacao : null,
            ]);
            PedidoRepository::inserirItens($id, $linhas);

            if (!$temPreparo) {
                // Nada a preparar: baixa o estoque agora e o pedido já nasce servido (salão) ou pronto (delivery).
                EstoqueService::baixarPorPedido($id, null);
                PedidoRepository::definirEstoqueBaixado($id, true);
                PedidoRepository::atualizarStatus($id, $presencial ? PedidoStatus::ENTREGUE : PedidoStatus::PRONTO);
            }

            if ($formaIntegral !== null) {
                PagamentoService::registrar($id, $formaIntegral, Dinheiro::reais($totalCentavos));
            } elseif ($pagamentos !== []) {
                PagamentoService::registrarMultiplos($id, $pagamentos);
            }
            return $id;
        });

        MesaService::sincronizar($idMesa ? (int) $idMesa : null);
        $pedido = PedidoRepository::buscar($idPedido);
        NotificacaoService::enviar($pedido, 'confirmacao');
        if (!$temPreparo) {
            self::sincronizarExterno($pedido);
            $pedido = PedidoRepository::buscar($idPedido);
        }

        return self::formatarLista([$pedido])[0];
    }

    /* ══════════════ Fluxo de status (kanban) ══════════════ */

    /**
     * Move o pedido para o próximo status, aplicando as regras de cada etapa.
     * @param int|null $idEntregador obrigatório ao mandar um delivery para "saiu" (se ainda não houver um)
     */
    public static function mudarStatus(int $id, string $para, ?int $idUsuario, ?int $idEntregador = null): array
    {
        if (!in_array($para, PedidoStatus::TODOS, true)) {
            throw new RegraNegocioException('Status inválido.');
        }

        $antes = Transacao::executar(function () use ($id, $para, $idUsuario, $idEntregador) {
            $p = PedidoRepository::buscarBruto($id, true);
            if ($p === null) {
                throw new RegraNegocioException('Pedido não encontrado.');
            }
            self::transicionar($p, $para, $idUsuario, $idEntregador);
            // Movido pelo quadro: os itens de todos os setores acompanham o pedido
            PedidoRepository::alinharItens($id, $para, PeriodoService::agora()->format('Y-m-d H:i:s'));
            return $p;
        });

        return self::depoisDaTransicao($id, $para, $antes);
    }

    /**
     * Telas da Cozinha e do Bar: cada setor inicia e conclui SÓ os próprios itens.
     *   iniciar → itens do setor "em preparo" (o 1º setor a começar baixa o estoque e põe o pedido "em preparo")
     *   pronto  → itens do setor "pronto" (quando o último setor termina, o pedido fica "pronto")
     */
    public static function avancarSetor(int $id, string $setor, string $etapa, ?int $idUsuario): array
    {
        if (!in_array($setor, SetorPreparo::COM_PREPARO, true)) {
            throw new RegraNegocioException('Setor de preparo inválido.');
        }
        if (!in_array($etapa, ['iniciar', 'pronto'], true)) {
            throw new RegraNegocioException('Ação de preparo inválida.');
        }

        [$antes, $novoStatus] = Transacao::executar(function () use ($id, $setor, $etapa, $idUsuario) {
            $p = PedidoRepository::buscarBruto($id, true);
            if ($p === null) {
                throw new RegraNegocioException('Pedido não encontrado.');
            }
            if (!in_array($p['status'], [PedidoStatus::NOVO, PedidoStatus::EM_PREPARO], true)) {
                throw new RegraNegocioException(sprintf(
                    'O pedido #%d não está mais na fila de preparo (agora está "%s"). Atualize a tela.', $id, PedidoStatus::rotulo($p['status'])
                ));
            }
            $rotulo = $setor === SetorPreparo::BAR ? 'do bar' : 'da cozinha';

            if ($etapa === 'iniciar') {
                if (PedidoRepository::iniciarItensDoSetor($id, $setor, PeriodoService::agora()->format('Y-m-d H:i:s')) === 0) {
                    throw new RegraNegocioException("Não há itens $rotulo aguardando neste pedido.");
                }
                if ($p['status'] === PedidoStatus::NOVO) {
                    self::transicionar($p, PedidoStatus::EM_PREPARO, $idUsuario, null);
                    return [$p, PedidoStatus::EM_PREPARO];
                }
                return [$p, null];
            }

            if (PedidoRepository::concluirItensDoSetor($id, $setor) === 0) {
                throw new RegraNegocioException("Inicie o preparo dos itens $rotulo antes de marcar como pronto.");
            }
            if (PedidoRepository::contarPreparoPendente($id) === 0) {
                self::transicionar($p, PedidoStatus::PRONTO, $idUsuario, null);
                return [$p, PedidoStatus::PRONTO];
            }
            return [$p, null];
        });

        return $novoStatus !== null ? self::depoisDaTransicao($id, $novoStatus, $antes) : self::buscar($id);
    }

    /** RF21 — atribui (ou troca) o entregador de um pedido de delivery. */
    public static function atribuirEntregador(int $idPedido, int $idEntregador): array
    {
        Transacao::executar(function () use ($idPedido, $idEntregador) {
            $p = PedidoRepository::buscarBruto($idPedido, true);
            if ($p === null) {
                throw new RegraNegocioException('Pedido não encontrado.');
            }
            if (!in_array($p['status'], [PedidoStatus::NOVO, PedidoStatus::EM_PREPARO, PedidoStatus::PRONTO], true)) {
                throw new RegraNegocioException('O entregador só pode ser definido antes de o pedido sair para entrega.');
            }
            self::atribuirEntregadorInterno($p, $idEntregador, false);
        });
        return self::buscar($idPedido);
    }

    /* ══════════════ Pagamento e conta ══════════════ */

    /** RF22 — pagamento de um pedido no caixa (aceita conta dividida). */
    public static function pagar(int $idPedido, array $pagamentos): array
    {
        $resumo = PagamentoService::registrarMultiplos($idPedido, $pagamentos);
        $p = PedidoRepository::buscarBruto($idPedido);
        MesaService::sincronizar($p && $p['id_mesa'] ? (int) $p['id_mesa'] : null);
        return $resumo;
    }

    /**
     * Fechamento de conta da mesa com divisão (RF22): os pagamentos são distribuídos pelos
     * pedidos em aberto da mesa, do mais antigo ao mais novo. Pedidos "pronto" e quitados
     * são marcados como entregues; com tudo quitado a mesa fica livre.
     *
     * @param array $pagamentos [{forma, valor}, ...] (um por pessoa/forma)
     */
    /** Percentual da taxa de serviço do garçom (opcional, escolhida ao fechar a conta). */
    public const TAXA_SERVICO_PERCENTUAL = 10;

    /** Taxa em centavos sobre uma base em centavos (mesma conta no JavaScript da tela). */
    public static function taxaServico(int $baseCentavos): int
    {
        return (int) round($baseCentavos * self::TAXA_SERVICO_PERCENTUAL / 100);
    }

    /**
     * Fecha a conta da mesa. Com $comTaxa = true soma 10% de serviço ao total:
     * o cliente precisa pagar conta + taxa (pagamento integral); a parte que
     * excede os pedidos é gravada em taxa_servico (para o garçom), e não como venda.
     */
    public static function fecharContaMesa(int $idMesa, array $pagamentos, ?int $idUsuario, bool $comTaxa = false, ?int $idGarcom = null): array
    {
        if (MesaRepository::buscar($idMesa) === null) {
            throw new RegraNegocioException('Mesa não encontrada.');
        }
        if ($pagamentos === []) {
            throw new RegraNegocioException('Informe ao menos um pagamento.');
        }
        // A taxa pertence a um garçom: ele precisa existir e estar ativo.
        if ($comTaxa) {
            $g = $idGarcom ? GorjetaRepository::buscarGarcom($idGarcom) : null;
            if ($g === null || !(int) $g['ativo']) {
                throw new RegraNegocioException('Informe o garçom que atendeu a mesa para cobrar a taxa de serviço.');
            }
        }

        Transacao::executar(function () use ($idMesa, $pagamentos, $idUsuario, $comTaxa, $idGarcom) {
            $abertos = PedidoRepository::abertosDaMesa($idMesa);
            if ($abertos === []) {
                throw new RegraNegocioException('Esta mesa não possui conta em aberto.');
            }
            $saldos = [];
            foreach ($abertos as $p) {
                $saldos[(int) $p['id_pedido']] = Dinheiro::centavos($p['valor_total']) - Dinheiro::centavos($p['valor_pago']);
            }
            $totalSaldo = array_sum(array_map(fn($s) => max(0, $s), $saldos));

            $porPedido = [];   // id_pedido => [[forma, centavos], ...]
            $soma = 0;
            foreach ($pagamentos as $pg) {
                $forma = strtolower(trim((string) ($pg['forma'] ?? '')));
                $valor = Numero::paraFloat($pg['valor'] ?? null);
                if (!in_array($forma, FormaPagamento::validas(), true) || $valor === null || $valor <= 0) {
                    throw new RegraNegocioException('Pagamento inválido: informe forma e valor maiores que zero.');
                }
                $restante = Dinheiro::centavos($valor);
                $soma += $restante;
                foreach ($saldos as $idPedido => &$saldo) {
                    if ($restante <= 0) {
                        break;
                    }
                    $uso = min($restante, max(0, $saldo));
                    if ($uso > 0) {
                        $porPedido[$idPedido][] = ['forma' => $forma, 'valor' => Dinheiro::reais($uso)];
                        $saldo -= $uso;
                        $restante -= $uso;
                    }
                }
                unset($saldo);
            }
            $taxa = $comTaxa ? self::taxaServico($totalSaldo) : 0;
            if ($comTaxa && $soma !== $totalSaldo + $taxa) {
                throw new RegraNegocioException(sprintf(
                    'Com a taxa de serviço de %d%% o total a pagar é %s (conta %s + taxa %s); informado: %s.',
                    self::TAXA_SERVICO_PERCENTUAL, Dinheiro::formatar($totalSaldo + $taxa),
                    Dinheiro::formatar($totalSaldo), Dinheiro::formatar($taxa), Dinheiro::formatar($soma)
                ));
            }
            if ($soma > $totalSaldo + $taxa) {
                throw new RegraNegocioException(sprintf(
                    'O total informado (%s) excede o saldo da conta (%s).', Dinheiro::formatar($soma), Dinheiro::formatar($totalSaldo)
                ));
            }
            if ($taxa > 0) {
                PedidoRepository::registrarTaxaServico($idMesa, $idUsuario, $idGarcom, $totalSaldo, self::TAXA_SERVICO_PERCENTUAL, $taxa);
            }

            foreach ($porPedido as $idPedido => $lista) {
                PagamentoService::registrarMultiplos((int) $idPedido, $lista);
            }

            // Quitado + pronto => entregue (cliente já foi servido)
            foreach ($abertos as $p) {
                if ($saldos[(int) $p['id_pedido']] <= 0 && $p['status'] === PedidoStatus::PRONTO) {
                    PedidoRepository::atualizarStatus((int) $p['id_pedido'], PedidoStatus::ENTREGUE);
                    PedidoRepository::alinharItens((int) $p['id_pedido'], PedidoStatus::ENTREGUE, PeriodoService::agora()->format('Y-m-d H:i:s'));
                }
            }
        });

        MesaService::sincronizar($idMesa);
        foreach (MesaService::listar() as $m) {
            if ($m['id_mesa'] === $idMesa) {
                return $m;
            }
        }
        return [];
    }

    /* ══════════════ Atrasos ══════════════ */

    public static function atrasoMinutos(): int
    {
        $cfg = require __DIR__ . '/../../config/integracoes.php';
        return max(1, (int) ($cfg['atraso_minutos'] ?? 20));
    }

    /** Envia (uma única vez por pedido) o aviso de atraso ao cliente. */
    private static function notificarAtrasos(array $linhas): void
    {
        foreach ($linhas as $p) {
            if (self::minutosEmPreparo($p) >= self::atrasoMinutos() && $p['status'] === PedidoStatus::EM_PREPARO
                && !NotificacaoRepository::existe((int) $p['id_pedido'], 'atraso')) {
                NotificacaoService::enviar($p, 'atraso');
            }
        }
    }

    private static function minutosEmPreparo(array $p): int
    {
        if ($p['status'] !== PedidoStatus::EM_PREPARO || empty($p['data_hora_inicio_preparo'])) {
            return 0;
        }
        $inicio = new DateTimeImmutable($p['data_hora_inicio_preparo'], new DateTimeZone('America/Sao_Paulo'));
        return max(0, (int) floor((PeriodoService::agora()->getTimestamp() - $inicio->getTimestamp()) / 60));
    }

    /* ══════════════ Internos ══════════════ */

    /**
     * Aplica uma mudança de status do pedido (chamar dentro de transação, com a linha travada):
     * valida o fluxo e executa as regras da etapa (estoque, entregador).
     */
    private static function transicionar(array $p, string $para, ?int $idUsuario, ?int $idEntregador): void
    {
        $id = (int) $p['id_pedido'];
        $de = $p['status'];
        if (!PedidoStatus::podeTransicionar($de, $para, (string) $p['canal'])) {
            throw new RegraNegocioException(sprintf(
                'Não é possível mover o pedido de "%s" para "%s".', PedidoStatus::rotulo($de), PedidoStatus::rotulo($para)
            ));
        }

        $inicioPreparo = null;
        switch ($para) {
            case PedidoStatus::EM_PREPARO:
                // Confirmar o pedido = dar baixa no estoque pela ficha técnica (RF15)
                if (!(int) $p['estoque_baixado']) {
                    EstoqueService::baixarPorPedido($id, $idUsuario);
                    PedidoRepository::definirEstoqueBaixado($id, true);
                }
                $inicioPreparo = PeriodoService::agora()->format('Y-m-d H:i:s');
                break;

            case PedidoStatus::SAIU:
                $idEntregador = $idEntregador ?: ($p['id_entregador'] ? (int) $p['id_entregador'] : null);
                if (!$idEntregador) {
                    throw new RegraNegocioException('Escolha o entregador para enviar o pedido.');
                }
                self::atribuirEntregadorInterno($p, $idEntregador, true);
                break;

            case PedidoStatus::CANCELADO:
                if ((int) $p['estoque_baixado']) {
                    EstoqueService::estornarPorPedido($id, $idUsuario);
                    PedidoRepository::definirEstoqueBaixado($id, false);
                }
                break;
        }

        PedidoRepository::atualizarStatus($id, $para, $inicioPreparo);

        // Entregue/cancelado: libera o entregador se ele não tiver outra entrega em rota
        if (in_array($para, [PedidoStatus::ENTREGUE, PedidoStatus::CANCELADO], true) && $p['id_entregador']) {
            self::liberarEntregadorSePossivel((int) $p['id_entregador'], $id);
        }
    }

    /** Efeitos fora da transação: mesa, repasse, aviso ao cliente e sincronização com a plataforma. */
    private static function depoisDaTransicao(int $id, string $para, array $antes): array
    {
        $pedido = PedidoRepository::buscar($id);
        MesaService::sincronizar($antes['id_mesa'] ? (int) $antes['id_mesa'] : null);

        if ($para === PedidoStatus::CANCELADO) {
            ContaService::cancelarDoPedido($id);   // o repasse previsto deixa de existir
        }
        if ($para === PedidoStatus::PRONTO) {
            NotificacaoService::enviar($pedido, 'pronto');
        }
        self::sincronizarExterno($pedido);

        return self::formatarLista([PedidoRepository::buscar($id)])[0];
    }

    /** Valida e grava o entregador no pedido; marca-o "em rota" apenas quando o pedido sai. */
    private static function atribuirEntregadorInterno(array $pedido, int $idEntregador, bool $saindo): void
    {
        if (CanalVenda::ehPresencial((string) $pedido['canal'])) {
            throw new RegraNegocioException('Entregador só se aplica a pedidos de delivery.');
        }
        $e = EntregadorRepository::buscar($idEntregador, true);
        if ($e === null) {
            throw new RegraNegocioException('Entregador não encontrado.');
        }
        if ($e['status'] === 'inativo') {
            throw new RegraNegocioException('Este entregador está inativo.');
        }
        $jaEhDele = (int) $pedido['id_entregador'] === $idEntregador;
        if ($e['status'] === 'em_rota' && !$jaEhDele) {
            throw new RegraNegocioException('Este entregador já está em rota com outra entrega.');
        }

        // Troca de entregador: libera o anterior
        if ($pedido['id_entregador'] && !$jaEhDele) {
            self::liberarEntregadorSePossivel((int) $pedido['id_entregador'], (int) $pedido['id_pedido']);
        }
        PedidoRepository::definirEntregador((int) $pedido['id_pedido'], $idEntregador);
        if ($saindo) {
            EntregadorRepository::definirStatus($idEntregador, 'em_rota');
        }
    }

    private static function liberarEntregadorSePossivel(int $idEntregador, int $ignorarPedido): void
    {
        $e = EntregadorRepository::buscar($idEntregador);
        if ($e !== null && $e['status'] === 'em_rota' && PedidoRepository::contarEntregasEmRota($idEntregador, $ignorarPedido) === 0) {
            EntregadorRepository::definirStatus($idEntregador, 'disponivel');
        }
    }

    /** RNF11: sincroniza o novo status com a plataforma de origem, via adapter. */
    private static function sincronizarExterno(?array $pedido): void
    {
        if ($pedido === null || empty($pedido['plataforma_externa']) || empty($pedido['id_pedido_externo'])) {
            return;
        }
        try {
            $ok = Adapters::plataformaExterna()->sincronizarStatus($pedido['plataforma_externa'], $pedido['id_pedido_externo'], $pedido['status']);
            PedidoRepository::definirSincronizado((int) $pedido['id_pedido'], $ok);
        } catch (Throwable $e) {
            error_log('[FoodHub] Falha ao sincronizar status externo: ' . $e->getMessage());
            PedidoRepository::definirSincronizado((int) $pedido['id_pedido'], false);
        }
    }

    /** @param array[] $linhas linhas de PedidoRepository (com mesa/entregador/cliente/valor_pago) */
    private static function formatarLista(array $linhas): array
    {
        $itens = PedidoRepository::itens(array_column($linhas, 'id_pedido'));
        $limite = self::atrasoMinutos();
        $saida = [];

        foreach ($linhas as $p) {
            $id = (int) $p['id_pedido'];
            $total = Dinheiro::centavos($p['valor_total']);
            $pago = Dinheiro::centavos($p['valor_pago'] ?? 0);
            $minutos = self::minutosEmPreparo($p);
            $nome = trim((string) (($p['nome_cliente'] ?? '') ?: ($p['usuario_nome'] ?? '')));

            $saida[] = [
                'id_pedido'        => $id,
                'canal'            => $p['canal'],
                'canal_rotulo'     => CanalVenda::rotulo((string) $p['canal']),
                'presencial'       => CanalVenda::ehPresencial((string) $p['canal']),
                'status'           => $p['status'],
                'status_rotulo'    => PedidoStatus::rotulo($p['status']),
                'proximos'         => PedidoStatus::proximos($p['status'], (string) $p['canal']),
                'data_hora'        => $p['data_hora'],
                'inicio_preparo'   => $p['data_hora_inicio_preparo'],
                'minutos_em_preparo' => $minutos,
                'atrasado'         => $p['status'] === PedidoStatus::EM_PREPARO && $minutos >= $limite,
                'valor_total'      => Dinheiro::reais($total),
                'valor_pago'       => Dinheiro::reais($pago),
                'saldo'            => Dinheiro::reais(max(0, $total - $pago)),
                'quitado'          => $pago >= $total,
                'mesa_numero'      => $p['mesa_numero'] !== null ? (int) $p['mesa_numero'] : null,
                'id_mesa'          => $p['id_mesa'] !== null ? (int) $p['id_mesa'] : null,
                'id_entregador'    => $p['id_entregador'] !== null ? (int) $p['id_entregador'] : null,
                'entregador'       => $p['entregador_nome'] ?? null,
                'id_garcom'        => $p['id_garcom'] !== null ? (int) $p['id_garcom'] : null,
                'garcom'           => $p['garcom_nome'] ?? null,
                'cliente'          => $nome !== '' ? $nome : null,
                'telefone'         => ($p['telefone_cliente'] ?? '') ?: ($p['usuario_telefone'] ?? null),
                'endereco_entrega' => $p['endereco_entrega'],
                'observacao'       => $p['observacao'],
                'plataforma_externa' => $p['plataforma_externa'],
                'id_pedido_externo'  => $p['id_pedido_externo'],
                'sincronizado'     => (bool) $p['sincronizado_status_externo'],
                'itens'            => array_map(fn($i) => [
                    'id_produto' => (int) $i['id_produto'], 'nome' => $i['nome'], 'quantidade' => (int) $i['quantidade'],
                    'preco_unitario' => (float) $i['preco_unitario'], 'observacao' => $i['observacao'],
                    'setor_preparo' => $i['setor_preparo'] ?? SetorPreparo::COZINHA,
                    'setor_rotulo' => SetorPreparo::rotulo($i['setor_preparo'] ?? SetorPreparo::COZINHA),
                    'status_item' => $i['status_item'] ?? SetorPreparo::ITEM_AGUARDANDO,
                    'status_item_rotulo' => SetorPreparo::ROTULOS_ITEM[$i['status_item'] ?? SetorPreparo::ITEM_AGUARDANDO] ?? '',
                    'inicio_preparo' => $i['data_hora_inicio_preparo'] ?? null,
                ], $itens[$id] ?? []),
            ];
        }
        return $saida;
    }
}
