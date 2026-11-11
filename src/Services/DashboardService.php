<?php

require_once __DIR__ . '/../Repositories/DashboardRepository.php';
require_once __DIR__ . '/../Repositories/RelatorioRepository.php';
require_once __DIR__ . '/../Repositories/FechamentoCaixaRepository.php';
require_once __DIR__ . '/../Models/CanalVenda.php';
require_once __DIR__ . '/../Models/FormaPagamento.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RelatorioService.php';
require_once __DIR__ . '/PedidoService.php';
require_once __DIR__ . '/ContaService.php';
require_once __DIR__ . '/../Repositories/PedidoRepository.php';

/**
 * RF25 — monta o payload do dashboard. Indicadores = hoje; gráficos = últimos 7 dias.
 */
class DashboardService
{
    private const DIAS_GRAFICO = 7;

    public static function indicadores(): array
    {
        $hoje = PeriodoService::dia(null);
        $janela = PeriodoService::intervalo(
            (new DateTimeImmutable($hoje['inicio']))->modify('-' . (self::DIAS_GRAFICO - 1) . ' days')->format('Y-m-d'),
            $hoje['fim']
        );

        $resumo  = RelatorioRepository::resumo($hoje['de'], $hoje['ate']);
        $pedidos = (int) $resumo['pedidos'];
        $total   = Dinheiro::centavos($resumo['total']);

        $estoqueBaixo = DashboardRepository::insumosAbaixoDoMinimo();
        $fluxo = ContaService::fluxo([]);   // mês corrente
        $recentes  = PedidoService::recentes(6);
        $atrasados = count(array_filter(PedidoService::cozinha()['pedidos'], fn($p) => $p['atrasado']));

        return [
            'gerado_em'   => PeriodoService::agora()->format('d/m/Y H:i:s'),
            'indicadores' => [
                'vendas_hoje'         => ['valor' => Dinheiro::reais($total), 'pedidos' => $pedidos],
                'ticket_medio'        => $pedidos > 0 ? Dinheiro::reais((int) round($total / $pedidos)) : 0.0,
                'pedidos_em_aberto'   => DashboardRepository::contarPedidosEmAberto(),
                'pedidos_atrasados'   => $atrasados,
                'financeiro'          => [
                    'saldo_mes'       => $fluxo['saldo'],
                    'a_receber'       => $fluxo['a_receber'],
                    'a_receber_vencido' => $fluxo['a_receber_vencido'],
                    'a_pagar'         => $fluxo['a_pagar'],
                    'pagar_vencidas'  => $fluxo['qtd_pagar_vencidas'],
                    'a_pagar_vencido' => $fluxo['a_pagar_vencido'],
                ],
                'pedidos_por_status'  => PedidoRepository::contarPorStatusNoDia($hoje['de'], $hoje['ate']),
                'ultimos_pedidos'     => array_map(fn($p) => [
                    'id_pedido' => $p['id_pedido'], 'canal_rotulo' => $p['canal_rotulo'], 'mesa_numero' => $p['mesa_numero'],
                    'status' => $p['status'], 'status_rotulo' => $p['status_rotulo'], 'data_hora' => $p['data_hora'],
                    'valor_total' => $p['valor_total'], 'atrasado' => $p['atrasado'],
                ], $recentes),
                'estoque_baixo'       => [
                    'quantidade' => count($estoqueBaixo),
                    'itens'      => array_map(fn($i) => [
                        'nome'           => $i['nome'],
                        'unidade'        => $i['unidade_medida'],
                        'estoque_atual'  => (float) $i['estoque_atual'],
                        'estoque_minimo' => (float) $i['estoque_minimo'],
                    ], array_slice($estoqueBaixo, 0, 8)),
                ],
            ],
            'graficos' => [
                'janela_dias'      => self::DIAS_GRAFICO,
                'vendas_diarias'   => self::graficoVendasDiarias($janela),
                'canais'           => self::graficoCanais($janela),
                'formas_pagamento' => self::graficoFormas($janela),
                'top_produtos'     => self::graficoProdutos($janela),
            ],
        ];
    }

    private static function graficoVendasDiarias(array $janela): array
    {
        $labels = $valores = [];
        foreach (RelatorioService::serieDiaria($janela) as $d) {
            $labels[]  = (new DateTimeImmutable($d['dia']))->format('d/m');
            $valores[] = Dinheiro::reais($d['total']);
        }
        return ['labels' => $labels, 'valores' => $valores];
    }

    private static function graficoCanais(array $janela): array
    {
        $labels = $valores = [];
        foreach (RelatorioRepository::vendasPorCanal($janela['de'], $janela['ate']) as $r) {
            $labels[]  = CanalVenda::rotulo($r['canal']);
            $valores[] = Dinheiro::reais(Dinheiro::centavos($r['total']));
        }
        return ['labels' => $labels, 'valores' => $valores];
    }

    private static function graficoFormas(array $janela): array
    {
        $labels = $valores = [];
        foreach (FechamentoCaixaRepository::totaisPorFormaPagamento($janela['de'], $janela['ate']) as $r) {
            $labels[]  = FormaPagamento::rotulo($r['forma']);
            $valores[] = Dinheiro::reais(Dinheiro::centavos($r['total']));
        }
        return ['labels' => $labels, 'valores' => $valores];
    }

    private static function graficoProdutos(array $janela): array
    {
        $labels = $valores = [];
        foreach (RelatorioRepository::produtosMaisVendidos($janela['de'], $janela['ate'], 5) as $r) {
            $labels[]  = $r['nome'];
            $valores[] = (int) $r['quantidade'];
        }
        return ['labels' => $labels, 'valores' => $valores];
    }
}
