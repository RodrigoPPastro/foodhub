<?php

require_once __DIR__ . '/../Repositories/PagamentoRepository.php';
require_once __DIR__ . '/../Models/FormaPagamento.php';
require_once __DIR__ . '/../Models/PedidoStatus.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * RF22 — registro da forma de pagamento. Suporta pagamento parcial e conta
 * dividida (vários pagamentos no mesmo pedido), conforme a entidade PAGAMENTO.
 *
 * Este Service também pode ser chamado direto pelo módulo de Pedidos (Pedro)
 * e pelo Delivery Próprio, sem passar por HTTP.
 */
class PagamentoService
{
    public static function registrar(int $idPedido, string $forma, $valor): array
    {
        return self::registrarMultiplos($idPedido, [['forma' => $forma, 'valor' => $valor]]);
    }

    /**
     * @param array<int,array{forma:string,valor:mixed}> $pagamentos
     * Tudo-ou-nada: se um item falhar, nenhum pagamento é gravado.
     */
    public static function registrarMultiplos(int $idPedido, array $pagamentos): array
    {
        if ($idPedido <= 0) {
            throw new RegraNegocioException('Pedido inválido.');
        }
        if ($pagamentos === []) {
            throw new RegraNegocioException('Informe ao menos uma forma de pagamento.');
        }

        $itens = self::normalizar($pagamentos);

        return PagamentoRepository::executarEmTransacao(function () use ($idPedido, $itens) {
            $pedido = PagamentoRepository::buscarPedidoParaPagamento($idPedido);
            if ($pedido === null) {
                throw new RegraNegocioException('Pedido não encontrado.');
            }
            if ($pedido['status'] === PedidoStatus::CANCELADO) {
                throw new RegraNegocioException('Pedido cancelado não aceita pagamento.');
            }

            $totalPedido = Dinheiro::centavos($pedido['valor_total']);
            $jaPago      = Dinheiro::centavos(PagamentoRepository::totalPagoDoPedido($idPedido));
            $novo        = array_sum(array_column($itens, 'centavos'));

            if ($jaPago + $novo > $totalPedido) {
                throw new RegraNegocioException(sprintf(
                    'O valor informado (%s) excede o saldo do pedido (%s).',
                    Dinheiro::formatar($novo),
                    Dinheiro::formatar($totalPedido - $jaPago)
                ));
            }

            $agora = PeriodoService::agora()->format('Y-m-d H:i:s');
            $ids = [];
            foreach ($itens as $item) {
                $idForma = PagamentoRepository::buscarIdForma($item['forma']);
                if ($idForma === null) {
                    throw new RegraNegocioException(
                        'Forma de pagamento não cadastrada no banco: ' . $item['forma'] . '.'
                    );
                }
                $ids[] = PagamentoRepository::inserir(
                    $idPedido, $idForma, Dinheiro::decimal($item['centavos']), $agora
                );
            }

            return self::resumo($totalPedido, $jaPago + $novo, $ids);
        });
    }

    public static function consultarPorPedido(int $idPedido): array
    {
        $pedido = PagamentoRepository::buscarPedido($idPedido);
        if ($pedido === null) {
            throw new RegraNegocioException('Pedido não encontrado.');
        }

        $pagamentos = array_map(fn($p) => [
            'id_pagamento' => (int) $p['id_pagamento'],
            'forma'        => $p['forma'],
            'rotulo'       => FormaPagamento::rotulo($p['forma']),
            'valor'        => Dinheiro::reais(Dinheiro::centavos($p['valor'])),
            'data_hora'    => $p['data_hora'],
        ], PagamentoRepository::listarPorPedido($idPedido));

        $pago = Dinheiro::centavos(PagamentoRepository::totalPagoDoPedido($idPedido));

        return ['pagamentos' => $pagamentos] + self::resumo(Dinheiro::centavos($pedido['valor_total']), $pago, []);
    }

    /** @return array<int,array{forma:string,centavos:int}> */
    private static function normalizar(array $pagamentos): array
    {
        $itens = [];
        foreach ($pagamentos as $p) {
            $forma = strtolower(trim((string) ($p['forma'] ?? '')));
            if (!in_array($forma, FormaPagamento::validas(), true)) {
                throw new RegraNegocioException(
                    'Forma de pagamento inválida. Use: ' . implode(', ', FormaPagamento::validas()) . '.'
                );
            }

            $bruto = $p['valor'] ?? null;
            if (is_string($bruto)) {
                $bruto = str_replace(',', '.', trim($bruto)); // aceita "12,50"
            }
            if (!is_numeric($bruto)) {
                throw new RegraNegocioException('Valor de pagamento inválido.');
            }

            $centavos = Dinheiro::centavos($bruto);
            if ($centavos <= 0) {
                throw new RegraNegocioException('O valor do pagamento deve ser maior que zero.');
            }
            $itens[] = ['forma' => $forma, 'centavos' => $centavos];
        }
        return $itens;
    }

    private static function resumo(int $totalPedido, int $pago, array $ids): array
    {
        return [
            'id_pagamentos' => $ids,
            'valor_pedido'  => Dinheiro::reais($totalPedido),
            'valor_pago'    => Dinheiro::reais($pago),
            'saldo'         => Dinheiro::reais($totalPedido - $pago),
            'quitado'       => $pago >= $totalPedido,
        ];
    }
}
