<?php

require_once __DIR__ . '/CanalVenda.php';
require_once __DIR__ . '/FormaPagamento.php';
require_once __DIR__ . '/../Services/Dinheiro.php';

/**
 * Fechamento de caixa de um dia (RF23). Todos os valores monetários ficam
 * em CENTAVOS (int) dentro do objeto, para evitar erros de ponto flutuante.
 */
class FechamentoCaixa
{
    /**
     * @param array<string,int>                            $porForma  forma => centavos
     * @param array<string,array{total:int,pedidos:int}>   $porCanal  canal => dados
     */
    public function __construct(
        public string $dataReferencia,
        public int $totalGeral,
        public array $porForma,
        public array $porCanal,
        public int $quantidadePedidos,
        public bool $fechado = false,
        public ?string $responsavel = null,
        public ?string $observacoes = null
    ) {
    }

    public function ticketMedio(): int
    {
        return $this->quantidadePedidos > 0
            ? (int) round($this->totalGeral / $this->quantidadePedidos)
            : 0;
    }

    public function totalDinheiro(): int
    {
        return $this->porForma['dinheiro'] ?? 0;
    }

    public function totalPix(): int
    {
        return $this->porForma['pix'] ?? 0;
    }

    /** Colunas legadas do DER: cartão = crédito + débito. */
    public function totalCartao(): int
    {
        $soma = 0;
        foreach ($this->porForma as $forma => $valor) {
            if (FormaPagamento::ehCartao($forma) || $forma === 'cartao') {
                $soma += $valor;
            }
        }
        return $soma;
    }

    /** Coluna legada do DER: soma de todos os canais que não são presenciais. */
    public function totalDelivery(): int
    {
        $soma = 0;
        foreach ($this->porCanal as $canal => $dados) {
            if (CanalVenda::ehDelivery($canal)) {
                $soma += $dados['total'];
            }
        }
        return $soma;
    }

    /** Snapshot gravado em fechamento_caixa.detalhamento. */
    public function detalhamento(): array
    {
        return [
            'por_forma'          => $this->porForma,
            'por_canal'          => $this->porCanal,
            'quantidade_pedidos' => $this->quantidadePedidos,
        ];
    }

    /** Estrutura pronta para JSON/PDF (valores em reais + rótulos + %). */
    public function toArray(): array
    {
        $formas = [];
        foreach ($this->porForma as $forma => $valor) {
            $formas[] = [
                'forma'      => $forma,
                'rotulo'     => FormaPagamento::rotulo($forma),
                'total'      => Dinheiro::reais($valor),
                'percentual' => Dinheiro::percentual($valor, $this->totalGeral),
            ];
        }

        $canais = [];
        foreach ($this->porCanal as $canal => $dados) {
            $canais[] = [
                'canal'      => $canal,
                'rotulo'     => CanalVenda::rotulo($canal),
                'pedidos'    => $dados['pedidos'],
                'total'      => Dinheiro::reais($dados['total']),
                'percentual' => Dinheiro::percentual($dados['total'], $this->totalGeral),
            ];
        }
        usort($canais, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'data_referencia'    => $this->dataReferencia,
            'fechado'            => $this->fechado,
            'responsavel'        => $this->responsavel,
            'observacoes'        => $this->observacoes,
            'total_geral'        => Dinheiro::reais($this->totalGeral),
            'quantidade_pedidos' => $this->quantidadePedidos,
            'ticket_medio'       => Dinheiro::reais($this->ticketMedio()),
            'por_forma'          => $formas,
            'por_canal'          => $canais,
        ];
    }
}
