<?php

require_once __DIR__ . '/../Repositories/MesaRepository.php';
require_once __DIR__ . '/../Repositories/PedidoRepository.php';
require_once __DIR__ . '/RegraNegocioException.php';

/** Mesas do salão (status livre/ocupada derivado dos pedidos em aberto). */
class MesaService
{
    public static function listar(): array
    {
        $mesas = [];
        foreach (MesaRepository::listar() as $m) {
            $saldo = 0.0;
            $consumido = 0.0;
            $pago = 0.0;
            $pedidos = [];
            $abertos = PedidoRepository::abertosDaMesa((int) $m['id_mesa']);
            foreach ($abertos as $p) {
                $s = max(0.0, round((float) $p['valor_total'] - (float) $p['valor_pago'], 2));
                $saldo += $s;
                $consumido += (float) $p['valor_total'];
                $pago += (float) $p['valor_pago'];
                $pedidos[] = ['id_pedido' => (int) $p['id_pedido'], 'status' => $p['status'], 'valor_total' => (float) $p['valor_total'],
                              'saldo' => $s, 'data_hora' => $p['data_hora'],
                              'cliente' => trim((string) ($p['nome_cliente'] ?? '')) ?: null,
                              'id_garcom' => $p['id_garcom'] !== null ? (int) $p['id_garcom'] : null, 'garcom' => $p['garcom_nome'] ?? null];
            }
            // Garçom da mesa = o do pedido mais recente que tiver um garçom escolhido
            $comGarcom = array_values(array_filter(array_reverse($pedidos), fn($x) => $x['id_garcom'] !== null));
            $status = $pedidos ? 'ocupada' : 'livre';
            if ($m['status'] !== $status) {
                MesaRepository::definirStatus((int) $m['id_mesa'], $status);
            }
            $mesas[] = [
                'id_mesa' => (int) $m['id_mesa'], 'numero' => (int) $m['numero'], 'capacidade' => (int) $m['capacidade'],
                'status' => $status, 'pedidos' => $pedidos, 'saldo' => round($saldo, 2),
                'id_garcom' => $comGarcom[0]['id_garcom'] ?? null, 'garcom' => $comGarcom[0]['garcom'] ?? null,
                'consumo' => self::consumo($abertos), 'total_consumido' => round($consumido, 2), 'ja_pago' => round($pago, 2),
                'aberta_desde' => $abertos ? $abertos[0]['data_hora'] : null,
            ];
        }
        return $mesas;
    }

    /**
     * Extrato da conta: tudo o que a mesa consumiu nos pedidos em aberto, somando o mesmo produto
     * pedido em rodadas diferentes (mesmo preço = mesma linha). Preço é o congelado na venda.
     */
    private static function consumo(array $pedidosAbertos): array
    {
        if ($pedidosAbertos === []) {
            return [];
        }
        $linhas = [];
        foreach (PedidoRepository::itens(array_column($pedidosAbertos, 'id_pedido')) as $itens) {
            foreach ($itens as $i) {
                $chave = $i['id_produto'] . '|' . $i['preco_unitario'];
                $linhas[$chave] ??= ['id_produto' => (int) $i['id_produto'], 'nome' => $i['nome'], 'quantidade' => 0,
                                     'preco_unitario' => (float) $i['preco_unitario'], 'subtotal' => 0.0];
                $linhas[$chave]['quantidade'] += (int) $i['quantidade'];
                $linhas[$chave]['subtotal'] = round($linhas[$chave]['quantidade'] * $linhas[$chave]['preco_unitario'], 2);
            }
        }
        return array_values($linhas);   // na ordem em que foram pedidos
    }

    /** Recalcula e grava o status da mesa após qualquer mudança em pedido/pagamento. */
    public static function sincronizar(?int $idMesa): void
    {
        if (!$idMesa || MesaRepository::buscar($idMesa) === null) {
            return;
        }
        MesaRepository::definirStatus($idMesa, PedidoRepository::abertosDaMesa($idMesa) !== [] ? 'ocupada' : 'livre');
    }

    public static function salvar(array $e): array
    {
        $id = (int) ($e['id_mesa'] ?? 0);
        $numero = filter_var($e['numero'] ?? null, FILTER_VALIDATE_INT);
        $cap = filter_var($e['capacidade'] ?? null, FILTER_VALIDATE_INT);
        if ($numero === false || $numero < 1 || $numero > 999) {
            throw new RegraNegocioException('O número da mesa deve estar entre 1 e 999.');
        }
        if ($cap === false || $cap < 1 || $cap > 30) {
            throw new RegraNegocioException('A capacidade deve estar entre 1 e 30 lugares.');
        }
        if (MesaRepository::numeroExiste($numero, $id)) {
            throw new RegraNegocioException('Já existe uma mesa com este número.');
        }
        if ($id > 0) {
            if (MesaRepository::buscar($id) === null) {
                throw new RegraNegocioException('Mesa não encontrada.');
            }
            MesaRepository::atualizar($id, $numero, $cap);
        } else {
            $id = MesaRepository::inserir($numero, $cap);
        }
        return ['id_mesa' => $id];
    }

    public static function excluir(int $id): array
    {
        if (MesaRepository::buscar($id) === null) {
            throw new RegraNegocioException('Mesa não encontrada.');
        }
        if (PedidoRepository::abertosDaMesa($id) !== []) {
            throw new RegraNegocioException('Não é possível excluir uma mesa ocupada. Feche a conta antes.');
        }
        MesaRepository::excluir($id);
        return ['id_mesa' => $id];
    }
}
