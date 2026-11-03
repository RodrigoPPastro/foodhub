<?php

require_once __DIR__ . '/../Repositories/FechamentoCaixaRepository.php';
require_once __DIR__ . '/../Models/FechamentoCaixa.php';
require_once __DIR__ . '/../Models/FormaPagamento.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/PdfService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * RF23 — fechamento de caixa diário.
 * Fluxo: consultar() mostra a PRÉVIA ao vivo → fechar() grava o snapshot
 * (um por dia) → depois disso o PDF e a tela passam a ler o snapshot,
 * de modo que um fechamento antigo nunca muda se os pedidos forem editados.
 */
class FechamentoCaixaService
{
    /** Cálculo ao vivo a partir de PAGAMENTO (dinheiro recebido no dia). */
    public static function calcular(string $data): FechamentoCaixa
    {
        $p = PeriodoService::dia($data);

        $porForma = array_fill_keys(FormaPagamento::validas(), 0); // formas sem venda aparecem zeradas
        foreach (FechamentoCaixaRepository::totaisPorFormaPagamento($p['de'], $p['ate']) as $linha) {
            $porForma[$linha['forma']] = ($porForma[$linha['forma']] ?? 0) + Dinheiro::centavos($linha['total']);
        }

        $porCanal = [];
        $pedidos = 0;
        foreach (FechamentoCaixaRepository::totaisPorCanal($p['de'], $p['ate']) as $linha) {
            $porCanal[$linha['canal']] = [
                'total'   => Dinheiro::centavos($linha['total']),
                'pedidos' => (int) $linha['pedidos'],
            ];
            $pedidos += (int) $linha['pedidos'];
        }

        return new FechamentoCaixa($p['inicio'], array_sum($porForma), $porForma, $porCanal, $pedidos);
    }

    /** Prévia ou fechamento já gravado, no formato da tela/PDF. */
    public static function consultar(string $data): array
    {
        $p = PeriodoService::dia($data);
        $salvo = FechamentoCaixaRepository::buscarPorData($p['inicio']);

        if ($salvo !== null) {
            $fechamento = self::hidratar($salvo);
            $pendencias = [];
        } else {
            $fechamento = self::calcular($p['inicio']);
            $pendencias = self::pendencias($p);
        }

        return $fechamento->toArray() + [
            'pendencias' => $pendencias,
            'pode_fechar' => !$fechamento->fechado && $pendencias === [] && $fechamento->totalGeral > 0,
        ];
    }

    public static function fechar(int $idUsuario, string $data, ?string $observacoes): array
    {
        $p = PeriodoService::dia($data);

        if ($p['inicio'] > PeriodoService::hoje()) {
            throw new RegraNegocioException('Não é possível fechar o caixa de uma data futura.');
        }
        if (FechamentoCaixaRepository::buscarPorData($p['inicio']) !== null) {
            throw new RegraNegocioException('O caixa desta data já foi fechado.');
        }

        $pendencias = self::pendencias($p);
        if ($pendencias !== []) {
            throw new RegraNegocioException(sprintf(
                'Existem %d pedido(s) com pagamento pendente nesta data. Regularize antes de fechar o caixa.',
                count($pendencias)
            ));
        }

        $fechamento = self::calcular($p['inicio']);
        if ($fechamento->totalGeral <= 0) {
            throw new RegraNegocioException('Não há pagamentos registrados nesta data para fechar.');
        }

        $observacoes = $observacoes !== null ? trim($observacoes) : null;
        if ($observacoes !== null && mb_strlen($observacoes) > 500) {
            throw new RegraNegocioException('As observações devem ter no máximo 500 caracteres.');
        }

        try {
            FechamentoCaixaRepository::inserir([
                'id_usuario'      => $idUsuario,
                'data_referencia' => $p['inicio'],
                'total_vendas'    => Dinheiro::decimal($fechamento->totalGeral),
                'total_dinheiro'  => Dinheiro::decimal($fechamento->totalDinheiro()),
                'total_cartao'    => Dinheiro::decimal($fechamento->totalCartao()),
                'total_pix'       => Dinheiro::decimal($fechamento->totalPix()),
                'total_delivery'  => Dinheiro::decimal($fechamento->totalDelivery()),
                'observacoes'     => ($observacoes === '') ? null : $observacoes,
                'detalhamento'    => json_encode($fechamento->detalhamento(), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            // UNIQUE(data_referencia): dois cliques/duas abas fechando ao mesmo tempo.
            if ($e->getCode() === '23000') {
                throw new RegraNegocioException('O caixa desta data já foi fechado.');
            }
            throw $e;
        }

        return self::consultar($p['inicio']);
    }

    /** @return array{conteudo:string,arquivo:string} */
    public static function gerarPdf(string $data): array
    {
        $dados = self::consultar($data);

        return [
            'conteudo' => PdfService::fechamentoCaixa($dados),
            'arquivo'  => 'fechamento_caixa_' . $dados['data_referencia'] . '.pdf', // data já validada
        ];
    }

    private static function pendencias(array $periodo): array
    {
        return array_map(function ($r) {
            $total = Dinheiro::centavos($r['valor_total']);
            $pago  = Dinheiro::centavos($r['pago']);
            return [
                'id_pedido'    => (int) $r['id_pedido'],
                'valor_pedido' => Dinheiro::reais($total),
                'valor_pago'   => Dinheiro::reais($pago),
                'saldo'        => Dinheiro::reais($total - $pago),
            ];
        }, FechamentoCaixaRepository::pedidosComPagamentoPendente($periodo['de'], $periodo['ate']));
    }

    /** Reconstrói o objeto a partir da linha gravada (snapshot JSON ou colunas do DER). */
    private static function hidratar(array $linha): FechamentoCaixa
    {
        $detalhe = !empty($linha['detalhamento']) ? json_decode($linha['detalhamento'], true) : null;

        if (is_array($detalhe) && isset($detalhe['por_forma'], $detalhe['por_canal'])) {
            $porForma = array_map('intval', $detalhe['por_forma']);
            $porCanal = $detalhe['por_canal'];
            $pedidos  = (int) ($detalhe['quantidade_pedidos'] ?? 0);
        } else {
            // Fechamento criado sem snapshot: só existem as colunas legadas do DER.
            $porForma = [
                'dinheiro' => Dinheiro::centavos($linha['total_dinheiro']),
                'pix'      => Dinheiro::centavos($linha['total_pix']),
                'cartao'   => Dinheiro::centavos($linha['total_cartao']),
            ];
            $porCanal = ['delivery' => ['total' => Dinheiro::centavos($linha['total_delivery']), 'pedidos' => 0]];
            $pedidos  = 0;
        }

        return new FechamentoCaixa(
            (string) $linha['data_referencia'],
            Dinheiro::centavos($linha['total_vendas']),
            $porForma,
            $porCanal,
            $pedidos,
            true,
            $linha['responsavel'] ?? null,
            $linha['observacoes'] ?? null
        );
    }
}
