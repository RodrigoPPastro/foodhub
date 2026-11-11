<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/ContaService.php';

/** Contas a pagar / a receber / fluxo de caixa (Master). */
class ContaController
{
    public static function listar(array $get): array { return RespostaController::executar(fn() => ContaService::listar($get)); }
    public static function fluxo(array $get): array { return RespostaController::executar(fn() => ContaService::fluxo($get)); }
    public static function canais(): array { return RespostaController::executar(fn() => ContaService::canais()); }
    public static function salvar(array $e): array { return RespostaController::executar(fn() => ContaService::salvar($e)); }
    public static function baixar(array $e, ?int $u): array
    {
        return RespostaController::executar(fn() => ContaService::baixar((int) ($e['id_conta'] ?? 0), $e['data_baixa'] ?? null, $e['valor'] ?? null, $u));
    }
    public static function baixarLote(array $e, ?int $u): array
    {
        return RespostaController::executar(fn() => ContaService::baixarLote(is_array($e['ids'] ?? null) ? $e['ids'] : [], $e['data_baixa'] ?? null, $u));
    }
    public static function estornar(array $e): array { return RespostaController::executar(fn() => ContaService::estornar((int) ($e['id_conta'] ?? 0))); }
    public static function cancelar(array $e): array { return RespostaController::executar(fn() => ContaService::cancelar((int) ($e['id_conta'] ?? 0))); }
    public static function salvarCanal(array $e): array
    {
        return RespostaController::executar(fn() => ContaService::salvarCanal((string) ($e['codigo'] ?? ''), $e['comissao_percentual'] ?? null, $e['prazo_repasse_dias'] ?? null));
    }
}
