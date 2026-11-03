<?php

require_once __DIR__ . '/../Repositories/RelatorioRepository.php';
require_once __DIR__ . '/../Models/CanalVenda.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * RF24 — relatórios gerenciais. Cada método devolve estrutura pronta para JSON.
 */
class RelatorioService
{
    public const TIPOS = ['vendas', 'produtos', 'insumos', 'ticket', 'canais'];
    private const LIMITE_MAXIMO = 50;

    public static function gerar(string $tipo, ?string $inicio, ?string $fim, int $limite = 10): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new RegraNegocioException('Tipo de relatório inválido.');
        }
        $limite = max(1, min($limite, self::LIMITE_MAXIMO));
        $p = PeriodoService::intervalo($inicio, $fim);

        $dados = match ($tipo) {
            'vendas'   => self::vendas($p),
            'produtos' => self::produtos($p, $limite),
            'insumos'  => self::insumos($p),
            'ticket'   => self::ticket($p),
            'canais'   => self::canais($p),
        };

        return ['periodo' => ['inicio' => $p['inicio'], 'fim' => $p['fim']]] + $dados;
    }

    /** Vendas por dia, incluindo dias sem venda (zerados) para o gráfico não "pular". */
    public static function serieDiaria(array $p): array
    {
        $porDia = [];
        foreach (RelatorioRepository::vendasPorDia($p['de'], $p['ate']) as $r) {
            $porDia[$r['dia']] = ['pedidos' => (int) $r['pedidos'], 'total' => Dinheiro::centavos($r['total'])];
        }

        $serie = [];
        $dia = new DateTimeImmutable($p['inicio']);
        $ultimo = new DateTimeImmutable($p['fim']);
        for (; $dia <= $ultimo; $dia = $dia->modify('+1 day')) {
            $chave = $dia->format('Y-m-d');
            $serie[] = ['dia' => $chave] + ($porDia[$chave] ?? ['pedidos' => 0, 'total' => 0]);
        }
        return $serie;
    }

    private static function vendas(array $p): array
    {
        $linhas = [];
        $totalGeral = 0;
        $pedidosGeral = 0;
        foreach (self::serieDiaria($p) as $d) {
            $totalGeral += $d['total'];
            $pedidosGeral += $d['pedidos'];
            $linhas[] = [
                'dia'     => $d['dia'],
                'pedidos' => $d['pedidos'],
                'total'   => Dinheiro::reais($d['total']),
            ];
        }
        return [
            'linhas' => $linhas,
            'totais' => ['pedidos' => $pedidosGeral, 'total' => Dinheiro::reais($totalGeral)],
        ];
    }

    private static function produtos(array $p, int $limite): array
    {
        $linhas = array_map(fn($r) => [
            'id_produto' => (int) $r['id_produto'],
            'nome'       => $r['nome'],
            'quantidade' => (int) $r['quantidade'],
            'receita'    => Dinheiro::reais(Dinheiro::centavos($r['receita'])),
        ], RelatorioRepository::produtosMaisVendidos($p['de'], $p['ate'], $limite));

        return ['linhas' => $linhas];
    }

    private static function insumos(array $p): array
    {
        $custoTotal = 0;
        $linhas = [];
        foreach (RelatorioRepository::consumoInsumos($p['de'], $p['ate']) as $r) {
            $custo = Dinheiro::centavos($r['custo']);
            $custoTotal += $custo;
            $linhas[] = [
                'id_insumo' => (int) $r['id_insumo'],
                'nome'      => $r['nome'],
                'unidade'   => $r['unidade_medida'],
                'consumo'   => round((float) $r['consumo'], 3),
                'custo'     => Dinheiro::reais($custo),
            ];
        }
        return ['linhas' => $linhas, 'totais' => ['custo' => Dinheiro::reais($custoTotal)]];
    }

    private static function ticket(array $p): array
    {
        $resumo  = RelatorioRepository::resumo($p['de'], $p['ate']);
        $pedidos = (int) $resumo['pedidos'];
        $total   = Dinheiro::centavos($resumo['total']);

        $serie = array_map(fn($d) => [
            'dia'          => $d['dia'],
            'pedidos'      => $d['pedidos'],
            'ticket_medio' => $d['pedidos'] > 0 ? Dinheiro::reais((int) round($d['total'] / $d['pedidos'])) : 0.0,
        ], self::serieDiaria($p));

        return [
            'pedidos'      => $pedidos,
            'total'        => Dinheiro::reais($total),
            'ticket_medio' => $pedidos > 0 ? Dinheiro::reais((int) round($total / $pedidos)) : 0.0,
            'serie'        => $serie,
        ];
    }

    /** Comparativo absoluto e percentual entre canais (participação e distância do líder). */
    private static function canais(array $p): array
    {
        $dados = RelatorioRepository::vendasPorCanal($p['de'], $p['ate']);
        $totalGeral = 0;
        foreach ($dados as $r) {
            $totalGeral += Dinheiro::centavos($r['total']);
        }
        $lider = $dados !== [] ? Dinheiro::centavos($dados[0]['total']) : 0; // já vem ordenado DESC

        $linhas = [];
        foreach ($dados as $r) {
            $total   = Dinheiro::centavos($r['total']);
            $pedidos = (int) $r['pedidos'];
            $linhas[] = [
                'canal'                  => $r['canal'],
                'rotulo'                 => CanalVenda::rotulo($r['canal']),
                'pedidos'                => $pedidos,
                'total'                  => Dinheiro::reais($total),
                'ticket_medio'           => $pedidos > 0 ? Dinheiro::reais((int) round($total / $pedidos)) : 0.0,
                'participacao_percentual'=> Dinheiro::percentual($total, $totalGeral),
                'diferenca_vs_lider'     => Dinheiro::reais($total - $lider),
                'diferenca_percentual_vs_lider' => $lider > 0 ? round(($total - $lider) * 100 / $lider, 1) : 0.0,
            ];
        }

        return ['linhas' => $linhas, 'totais' => ['total' => Dinheiro::reais($totalGeral)]];
    }
}
