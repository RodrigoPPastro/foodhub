<?php

require_once __DIR__ . '/../Repositories/GorjetaRepository.php';
require_once __DIR__ . '/Dinheiro.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';
require_once __DIR__ . '/../Repositories/Transacao.php';

/**
 * Taxa de serviço (10%) dos garçons: relatório por período e repasse.
 * A taxa é registrada ao fechar a conta da mesa (PedidoService::fecharContaMesa).
 * Aqui o Master vê quanto cada garçom tem a receber e marca como PAGO.
 */
class GorjetaService
{
    public static function garcons(bool $soAtivos = true): array
    {
        return array_map(fn($g) => ['id_garcom' => (int) $g['id_garcom'], 'nome' => $g['nome'], 'ativo' => (bool) $g['ativo']],
            GorjetaRepository::garcons($soAtivos));
    }

    public static function salvarGarcom(array $e): array
    {
        $nome = trim((string) ($e['nome'] ?? ''));
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            throw new RegraNegocioException('Informe o nome do garçom (2 a 80 caracteres).');
        }
        $id = (int) ($e['id_garcom'] ?? 0);
        try {
            if ($id > 0) {
                if (GorjetaRepository::buscarGarcom($id) === null) { throw new RegraNegocioException('Garçom não encontrado.'); }
                GorjetaRepository::atualizarGarcom($id, $nome, !isset($e['ativo']) || (bool) $e['ativo']);
            } else {
                $id = GorjetaRepository::inserirGarcom($nome);
            }
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') { throw new RegraNegocioException('Já existe um garçom com esse nome.'); }
            throw $ex;
        }
        return ['id_garcom' => $id];
    }

    /** Período [de, ate] (datas Y-m-d, inclusive) → resumo por garçom + lançamentos. */
    public static function relatorio(array $get): array
    {
        $per = PeriodoService::intervalo($get['de'] ?? null, $get['ate'] ?? null, 1);   // padrão: hoje
        $de = $per['de']; $ate = $per['ate'];
        $idG = isset($get['id_garcom']) && $get['id_garcom'] !== '' ? (int) $get['id_garcom'] : null;
        $linhas = GorjetaRepository::lancamentos($de, $ate, $idG);

        $porGarcom = [];
        $total = $pago = 0;
        foreach ($linhas as $l) {
            $c = Dinheiro::centavos($l['valor']);
            $chave = (int) $l['id_garcom'];
            $porGarcom[$chave] ??= ['id_garcom' => $chave ?: null, 'garcom' => $l['garcom'] ?? 'Sem garçom informado',
                                    'contas' => 0, 'total' => 0, 'pago' => 0, 'pendente' => 0];
            $porGarcom[$chave]['contas']++;
            $porGarcom[$chave]['total'] += $c;
            $porGarcom[$chave][$l['status'] === 'pago' ? 'pago' : 'pendente'] += $c;
            $total += $c;
            if ($l['status'] === 'pago') { $pago += $c; }
        }
        return [
            'periodo' => ['de' => $per['inicio'], 'ate' => $per['fim']],
            'totais' => ['total' => Dinheiro::reais($total), 'pago' => Dinheiro::reais($pago), 'pendente' => Dinheiro::reais($total - $pago)],
            'garcons' => array_values(array_map(fn($g) => array_merge($g, [
                'total' => Dinheiro::reais($g['total']), 'pago' => Dinheiro::reais($g['pago']), 'pendente' => Dinheiro::reais($g['pendente']),
            ]), $porGarcom)),
            'lancamentos' => array_map(fn($l) => [
                'id_taxa' => (int) $l['id_taxa'], 'mesa' => $l['mesa'] !== null ? (int) $l['mesa'] : null, 'garcom' => $l['garcom'],
                'base' => (float) $l['base'], 'valor' => (float) $l['valor'], 'status' => $l['status'],
                'data_hora' => $l['data_hora'], 'data_pagamento' => $l['data_pagamento'],
            ], $linhas),
        ];
    }

    /**
     * Paga o garçom: marca como pagas TODAS as taxas pendentes dele no período
     * (ou só as ids informadas). Falha se não houver nada pendente.
     */
    public static function pagar(array $e, ?int $idUsuario): array
    {
        $ids = array_filter(array_map('intval', is_array($e['ids'] ?? null) ? $e['ids'] : []));
        if ($ids === []) {
            $idG = (int) ($e['id_garcom'] ?? 0);
            if ($idG <= 0) { throw new RegraNegocioException('Informe o garçom a pagar.'); }
            $per = PeriodoService::intervalo($e['de'] ?? null, $e['ate'] ?? null, 1);
            $ids = array_map(fn($l) => (int) $l['id_taxa'], GorjetaRepository::lancamentos($per['de'], $per['ate'], $idG, 'pendente'));
        }
        $n = 0;
        Transacao::executar(function () use ($ids, $idUsuario, &$n) { $n = GorjetaRepository::marcarPagas($ids, $idUsuario); });
        if ($n === 0) { throw new RegraNegocioException('Não há taxa pendente para pagar neste período.'); }
        return ['pagos' => $n];
    }
}
