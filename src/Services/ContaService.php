<?php

require_once __DIR__ . '/../Repositories/ContaRepository.php';
require_once __DIR__ . '/../Repositories/PedidoRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/Numero.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * Contas a pagar e a receber.
 *
 * - A PAGAR: lançadas à mão (aluguel, luz, fornecedor…) ou geradas ao dar entrada de estoque.
 * - A RECEBER: (1) repasse de plataformas (iFood/Rappi), gerado AUTOMATICAMENTE para cada pedido
 *   externo = total do pedido − comissão do canal, vencendo depois do prazo de repasse do canal;
 *   (2) lançamentos avulsos (venda a prazo, evento…).
 * - "Vencida" não é um status gravado: é conta aberta cujo vencimento já passou (calculado na hora).
 * - O fechamento de caixa NÃO muda: continua olhando só os pagamentos dos pedidos. Aqui é uma visão
 *   financeira à parte, ligada ao pedido pelo id, para o fluxo de caixa refletir QUANDO o dinheiro entra.
 */
class ContaService
{
    public const CATEGORIAS = [
        'pagar' => [
            'insumos' => 'Insumos / fornecedores', 'aluguel' => 'Aluguel', 'energia' => 'Energia', 'agua' => 'Água',
            'internet' => 'Internet / telefone', 'folha' => 'Folha / salários', 'entregadores' => 'Entregadores',
            'impostos' => 'Impostos e taxas', 'manutencao' => 'Manutenção', 'outros' => 'Outros',
        ],
        'receber' => [
            'repasse_plataforma' => 'Repasse de plataforma', 'venda_prazo' => 'Venda a prazo', 'outros' => 'Outros',
        ],
    ];

    /* ══════════════ Consultas ══════════════ */

    public static function listar(array $get): array
    {
        $tipo = self::tipo($get['tipo'] ?? 'pagar');
        $situacao = (string) ($get['situacao'] ?? 'aberta');
        if (!in_array($situacao, ['aberta', 'vencida', 'baixada', 'cancelada', 'todas'], true)) {
            throw new RegraNegocioException('Situação inválida.');
        }
        $de = self::dataOpcional($get['de'] ?? null, 'Data inicial');
        $ate = self::dataOpcional($get['ate'] ?? null, 'Data final');
        if ($de && $ate && $de > $ate) {
            throw new RegraNegocioException('A data inicial não pode ser depois da final.');
        }
        if ($tipo === 'receber') {
            self::gerarRepassesPendentes();   // autocorreção: garante repasse de todo pedido externo
        }
        $hoje = PeriodoService::hoje();
        $busca = trim((string) ($get['busca'] ?? ''));
        $linhas = ContaRepository::listar(['tipo' => $tipo, 'situacao' => $situacao, 'de' => $de, 'ate' => $ate, 'busca' => $busca], $hoje);

        $t = ContaRepository::totais($tipo, $hoje, null, null);
        return [
            'contas' => array_map(fn($c) => self::formatar($c, $hoje), $linhas),
            'resumo' => [
                'em_aberto' => Dinheiro::reais(Dinheiro::centavos($t['aberto'])),
                'vencido' => Dinheiro::reais(Dinheiro::centavos($t['vencido'])),
                'qtd_vencidas' => (int) $t['qtd_vencidas'],
                'soma_listada' => Dinheiro::reais(array_sum(array_map(fn($c) => $c['status'] === 'cancelada' ? 0 : Dinheiro::centavos($c['valor']), $linhas))),
            ],
            'categorias' => self::CATEGORIAS[$tipo],
        ];
    }

    /** Fluxo de caixa do período (padrão: mês corrente). */
    public static function fluxo(array $get): array
    {
        $hoje = PeriodoService::hoje();
        $de = self::dataOpcional($get['de'] ?? null, 'Data inicial') ?: substr($hoje, 0, 8) . '01';
        $ate = self::dataOpcional($get['ate'] ?? null, 'Data final') ?: $hoje;
        if ($de > $ate) {
            throw new RegraNegocioException('A data inicial não pode ser depois da final.');
        }
        self::gerarRepassesPendentes();

        $vendas = Dinheiro::centavos(ContaRepository::vendasDiretasRecebidas($de, $ate));
        $recebido = Dinheiro::centavos(ContaRepository::totalBaixado('receber', $de, $ate));
        $pago = Dinheiro::centavos(ContaRepository::totalBaixado('pagar', $de, $ate));
        $aReceber = ContaRepository::totais('receber', $hoje, null, null);
        $aPagar = ContaRepository::totais('pagar', $hoje, null, null);

        return [
            'periodo' => ['de' => $de, 'ate' => $ate],
            'entradas_vendas_diretas' => Dinheiro::reais($vendas),
            'entradas_recebimentos' => Dinheiro::reais($recebido),
            'saidas_pagas' => Dinheiro::reais($pago),
            'saldo' => Dinheiro::reais($vendas + $recebido - $pago),
            'a_receber' => Dinheiro::reais(Dinheiro::centavos($aReceber['aberto'])),
            'a_receber_vencido' => Dinheiro::reais(Dinheiro::centavos($aReceber['vencido'])),
            'a_pagar' => Dinheiro::reais(Dinheiro::centavos($aPagar['aberto'])),
            'a_pagar_vencido' => Dinheiro::reais(Dinheiro::centavos($aPagar['vencido'])),
            'qtd_pagar_vencidas' => (int) $aPagar['qtd_vencidas'],
            'projecao' => Dinheiro::reais($vendas + $recebido - $pago + Dinheiro::centavos($aReceber['aberto']) - Dinheiro::centavos($aPagar['aberto'])),
        ];
    }

    public static function canais(): array
    {
        return array_map(fn($c) => [
            'codigo' => $c['codigo'], 'rotulo' => $c['rotulo'],
            'comissao_percentual' => (float) $c['comissao_percentual'], 'prazo_repasse_dias' => (int) $c['prazo_repasse_dias'],
        ], ContaRepository::canais());
    }

    /* ══════════════ Escrita ══════════════ */

    /** Cria ou edita uma conta MANUAL em aberto. */
    public static function salvar(array $e): array
    {
        $id = (int) ($e['id_conta'] ?? 0);
        $dados = self::validar($e);
        if ($id > 0) {
            $atual = ContaRepository::buscar($id);
            if ($atual === null) {
                throw new RegraNegocioException('Conta não encontrada.');
            }
            if ($atual['status'] !== 'aberta') {
                throw new RegraNegocioException('Só é possível editar contas em aberto. Estorne a baixa antes, se precisar corrigir.');
            }
            if ($atual['id_pedido'] !== null) {
                throw new RegraNegocioException('Repasses gerados pelos pedidos não podem ser editados. Ajuste o valor ao dar a baixa.');
            }
            if ($atual['tipo'] !== $dados['tipo']) {
                throw new RegraNegocioException('Não é possível mudar o tipo da conta.');
            }
            ContaRepository::atualizar($id, $dados);
        } else {
            $id = ContaRepository::inserir($dados);
        }
        return self::formatar(ContaRepository::buscar($id), PeriodoService::hoje());
    }

    /** Baixa = marcar como paga/recebida. $valor opcional: o valor que realmente foi pago/recebido. */
    public static function baixar(int $id, ?string $dataBaixa, $valor, ?int $idUsuario): array
    {
        $hoje = PeriodoService::hoje();
        $data = self::dataOpcional($dataBaixa, 'Data da baixa') ?: $hoje;
        if ($data > $hoje) {
            throw new RegraNegocioException('A data da baixa não pode ser no futuro.');
        }
        return Transacao::executar(function () use ($id, $data, $valor, $idUsuario, $hoje) {
            $c = ContaRepository::buscar($id, true);
            if ($c === null) {
                throw new RegraNegocioException('Conta não encontrada.');
            }
            if ($c['status'] !== 'aberta') {
                throw new RegraNegocioException('Esta conta não está em aberto.');
            }
            $final = $c['valor'];
            if ($valor !== null && $valor !== '') {
                $final = self::valorMonetario($valor, 'Valor da baixa');
            }
            ContaRepository::baixar($id, $data, $final, $idUsuario);
            return self::formatar(ContaRepository::buscar($id), $hoje);
        });
    }

    /** Baixa várias contas de uma vez (ex.: repasses do iFood da semana). */
    public static function baixarLote(array $ids, ?string $dataBaixa, ?int $idUsuario): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) {
            throw new RegraNegocioException('Selecione ao menos uma conta.');
        }
        if (count($ids) > 200) {
            throw new RegraNegocioException('Selecione no máximo 200 contas por vez.');
        }
        Transacao::executar(function () use ($ids, $dataBaixa, $idUsuario) {
            foreach ($ids as $id) {
                self::baixar($id, $dataBaixa, null, $idUsuario);   // se alguma falhar, nenhuma é baixada
            }
        });
        return ['baixadas' => count($ids)];
    }

    public static function estornar(int $id): array
    {
        return Transacao::executar(function () use ($id) {
            $c = ContaRepository::buscar($id, true);
            if ($c === null) {
                throw new RegraNegocioException('Conta não encontrada.');
            }
            if ($c['status'] !== 'baixada') {
                throw new RegraNegocioException('Só é possível estornar contas já baixadas.');
            }
            ContaRepository::reabrir($id);
            return self::formatar(ContaRepository::buscar($id), PeriodoService::hoje());
        });
    }

    public static function cancelar(int $id): array
    {
        return Transacao::executar(function () use ($id) {
            $c = ContaRepository::buscar($id, true);
            if ($c === null) {
                throw new RegraNegocioException('Conta não encontrada.');
            }
            if ($c['status'] !== 'aberta') {
                throw new RegraNegocioException('Só é possível cancelar contas em aberto (estorne a baixa antes).');
            }
            ContaRepository::definirStatus($id, 'cancelada');
            return self::formatar(ContaRepository::buscar($id), PeriodoService::hoje());
        });
    }

    public static function salvarCanal(string $codigo, $comissao, $prazo): array
    {
        $c = Numero::paraFloat($comissao);
        if ($c === null || $c < 0 || $c > 100) {
            throw new RegraNegocioException('A comissão deve estar entre 0% e 100%.');
        }
        if (!is_numeric($prazo) || (int) $prazo < 0 || (int) $prazo > 365 || (int) $prazo != $prazo) {
            throw new RegraNegocioException('O prazo de repasse deve ser de 0 a 365 dias.');
        }
        if (ContaRepository::buscarCanal($codigo) === null) {
            throw new RegraNegocioException('Canal não encontrado.');
        }
        ContaRepository::salvarCanal($codigo, number_format(round($c, 2), 2, '.', ''), (int) $prazo);
        return ['codigo' => $codigo, 'comissao_percentual' => round($c, 2), 'prazo_repasse_dias' => (int) $prazo];
    }

    /* ══════════════ Integração com pedidos / estoque ══════════════ */

    /**
     * Gera o repasse de um pedido de plataforma externa. Idempotente (um repasse por pedido).
     * Chamado quando o webhook cria o pedido e, por garantia, ao consultar as contas a receber.
     */
    public static function gerarRepasse(int $idPedido): ?int
    {
        $p = PedidoRepository::buscarBruto($idPedido);
        if ($p === null || $p['status'] === 'cancelado' || !CanalVenda::ehExterno((string) $p['canal'])) {
            return null;
        }
        if (ContaRepository::existeParaPedido($idPedido)) {
            return null;
        }
        $canal = ContaRepository::buscarCanal((string) $p['canal']);
        $comissao = (float) ($canal['comissao_percentual'] ?? 0);
        $prazo = (int) ($canal['prazo_repasse_dias'] ?? 0);

        $bruto = Dinheiro::centavos($p['valor_total']);
        $liquido = (int) round($bruto * (10000 - (int) round($comissao * 100)) / 10000);
        $emissao = substr((string) $p['data_hora'], 0, 10);
        $venc = (new DateTimeImmutable($emissao))->modify('+' . $prazo . ' days')->format('Y-m-d');
        $rotulo = $canal['rotulo'] ?? $p['canal'];

        return ContaRepository::inserir([
            'tipo' => 'receber', 'categoria' => 'repasse_plataforma', 'pessoa' => $rotulo,
            'descricao' => sprintf('Repasse %s — pedido #%d%s', $rotulo, $idPedido, $p['id_pedido_externo'] ? ' (' . $p['id_pedido_externo'] . ')' : ''),
            'valor' => Dinheiro::decimal($liquido), 'valor_bruto' => Dinheiro::decimal($bruto), 'comissao_percentual' => number_format($comissao, 2, '.', ''),
            'data_emissao' => $emissao, 'data_vencimento' => $venc, 'id_pedido' => $idPedido,
            'observacao' => sprintf('Total do pedido %s − comissão %s%%', Dinheiro::formatar($bruto), number_format($comissao, 2, ',', '')),
        ]);
    }

    public static function gerarRepassesPendentes(): void
    {
        foreach (ContaRepository::pedidosExternosSemRepasse() as $idPedido) {
            try {
                self::gerarRepasse((int) $idPedido);
            } catch (Throwable $e) {
                error_log('[FoodHub] repasse do pedido ' . $idPedido . ': ' . $e->getMessage());
            }
        }
    }

    /** Pedido cancelado: o repasse previsto deixa de existir (se ainda estiver em aberto). */
    public static function cancelarDoPedido(int $idPedido): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE conta_financeira SET status = 'cancelada' WHERE id_pedido = :p AND status = 'aberta'"
        );
        $stmt->execute(['p' => $idPedido]);
    }

    /** Valida uma conta a pagar vinda da entrada de estoque (antes de mexer no estoque). */
    public static function validarCompra(array $e): array
    {
        return self::validar([
            'tipo' => 'pagar', 'categoria' => 'insumos', 'descricao' => $e['descricao'] ?? '', 'pessoa' => $e['pessoa'] ?? '',
            'valor' => $e['valor'] ?? null, 'data_vencimento' => $e['data_vencimento'] ?? '',
        ]);
    }

    public static function inserirValidada(array $dados): int
    {
        return ContaRepository::inserir($dados);
    }

    /* ══════════════ Internos ══════════════ */

    private static function validar(array $e): array
    {
        $tipo = self::tipo($e['tipo'] ?? '');
        $descricao = trim((string) ($e['descricao'] ?? ''));
        $pessoa = trim((string) ($e['pessoa'] ?? ''));
        $obs = trim((string) ($e['observacao'] ?? ''));
        $categoria = (string) ($e['categoria'] ?? '');
        if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 150) {
            throw new RegraNegocioException('Informe a descrição (até 150 caracteres).');
        }
        if (mb_strlen($pessoa, 'UTF-8') > 100 || mb_strlen($obs, 'UTF-8') > 255) {
            throw new RegraNegocioException('Fornecedor/origem (100) ou observação (255) muito longos.');
        }
        if (!isset(self::CATEGORIAS[$tipo][$categoria])) {
            throw new RegraNegocioException('Escolha uma categoria válida.');
        }
        $venc = self::dataOpcional($e['data_vencimento'] ?? null, 'Vencimento');
        if (!$venc) {
            throw new RegraNegocioException('Informe a data de vencimento.');
        }
        return [
            'tipo' => $tipo, 'descricao' => $descricao, 'categoria' => $categoria, 'pessoa' => $pessoa !== '' ? $pessoa : null,
            'valor' => self::valorMonetario($e['valor'] ?? null, 'Valor'), 'data_emissao' => PeriodoService::hoje(),
            'data_vencimento' => $venc, 'observacao' => $obs !== '' ? $obs : null,
        ];
    }

    private static function tipo($t): string
    {
        if (!in_array($t, ['pagar', 'receber'], true)) {
            throw new RegraNegocioException('Tipo de conta inválido.');
        }
        return $t;
    }

    private static function valorMonetario($v, string $rotulo): string
    {
        $n = Numero::paraFloat($v);
        if ($n === null || $n <= 0 || $n > 9999999.99) {
            throw new RegraNegocioException("$rotulo deve ser maior que zero.");
        }
        return Dinheiro::decimal(Dinheiro::centavos($n));
    }

    private static function dataOpcional($v, string $rotulo): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || $d->format('Y') < 2000 || $d->format('Y') > 2100) {
            throw new RegraNegocioException("$rotulo inválida.");
        }
        return $v;
    }

    private static function formatar(array $c, string $hoje): array
    {
        $vencida = $c['status'] === 'aberta' && $c['data_vencimento'] < $hoje;
        return [
            'id_conta' => (int) $c['id_conta'], 'tipo' => $c['tipo'], 'descricao' => $c['descricao'],
            'categoria' => $c['categoria'], 'categoria_rotulo' => self::CATEGORIAS[$c['tipo']][$c['categoria']] ?? $c['categoria'],
            'pessoa' => $c['pessoa'], 'valor' => (float) $c['valor'],
            'valor_bruto' => $c['valor_bruto'] !== null ? (float) $c['valor_bruto'] : null,
            'comissao_percentual' => $c['comissao_percentual'] !== null ? (float) $c['comissao_percentual'] : null,
            'data_vencimento' => $c['data_vencimento'], 'data_baixa' => $c['data_baixa'], 'status' => $c['status'],
            'situacao' => $vencida ? 'vencida' : $c['status'],
            'dias_atraso' => $vencida ? (int) (new DateTimeImmutable($c['data_vencimento']))->diff(new DateTimeImmutable($hoje))->days : 0,
            'automatica' => $c['id_pedido'] !== null, 'id_pedido' => $c['id_pedido'] !== null ? (int) $c['id_pedido'] : null,
            'observacao' => $c['observacao'],
        ];
    }
}
