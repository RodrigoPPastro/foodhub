<?php

require_once __DIR__ . '/../Repositories/EntregadorRepository.php';
require_once __DIR__ . '/RegraNegocioException.php';

/** RF21 — cadastro de entregadores. */
class EntregadorService
{
    public static function listar(): array
    {
        return array_map(fn($e) => [
            'id_entregador' => (int) $e['id_entregador'], 'nome' => $e['nome'], 'telefone' => $e['telefone'],
            'veiculo' => $e['veiculo'], 'status' => $e['status'],
            'entregas_em_andamento' => (int) $e['entregas_em_andamento'], 'total_pedidos' => (int) $e['total_pedidos'],
        ], EntregadorRepository::listar());
    }

    public static function salvar(array $e): array
    {
        $id = (int) ($e['id_entregador'] ?? 0);
        $nome = trim((string) ($e['nome'] ?? ''));
        $tel = trim((string) ($e['telefone'] ?? ''));
        $veiculo = trim((string) ($e['veiculo'] ?? ''));
        $status = (string) ($e['status'] ?? 'disponivel');

        $len = mb_strlen($nome, 'UTF-8');
        if ($len < 3 || $len > 80) {
            throw new RegraNegocioException('O nome do entregador deve ter de 3 a 80 caracteres.');
        }
        if (!preg_match('/^\(\+55\)\d{2}-\d{8,9}$/', $tel)) {
            throw new RegraNegocioException('Telefone inválido. Use o formato (+55)XX-XXXXXXXX ou (+55)XX-XXXXXXXXX.');
        }
        if (mb_strlen($veiculo, 'UTF-8') > 40) {
            throw new RegraNegocioException('O veículo pode ter no máximo 40 caracteres.');
        }
        if (!in_array($status, ['disponivel', 'inativo'], true)) {
            throw new RegraNegocioException('Situação inválida (use Disponível ou Inativo).');
        }
        $dados = ['nome' => $nome, 'telefone' => $tel, 'veiculo' => $veiculo === '' ? null : $veiculo, 'status' => $status];

        if ($id > 0) {
            $atual = EntregadorRepository::buscar($id);
            if ($atual === null) {
                throw new RegraNegocioException('Entregador não encontrado.');
            }
            if ($atual['status'] === 'em_rota') {
                throw new RegraNegocioException('Este entregador está em rota. Finalize a entrega antes de editar a situação.');
            }
            EntregadorRepository::atualizar($id, $dados);
        } else {
            $id = EntregadorRepository::inserir($dados);
        }
        return ['id_entregador' => $id];
    }

    public static function excluir(int $id): array
    {
        if (EntregadorRepository::buscar($id) === null) {
            throw new RegraNegocioException('Entregador não encontrado.');
        }
        if (EntregadorRepository::temPedidos($id)) {
            throw new RegraNegocioException('Este entregador possui entregas no histórico e não pode ser excluído. Marque-o como inativo.');
        }
        EntregadorRepository::excluir($id);
        return ['id_entregador' => $id];
    }
}
