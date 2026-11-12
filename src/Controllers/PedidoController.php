<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/PedidoService.php';
require_once __DIR__ . '/../Services/MesaService.php';
require_once __DIR__ . '/../Services/EntregadorService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';

/** RF17–RF22 — pedidos, mesas, entregadores, notificações e webhook. */
class PedidoController
{
    public static function quadro(array $get): array { return RespostaController::executar(fn() => PedidoService::quadro($get)); }
    /** @param string|null $setor cozinha | bar | null (todos) */
    public static function cozinha(?string $setor = null): array { return RespostaController::executar(fn() => PedidoService::cozinha($setor)); }
    public static function buscar(array $get): array { return RespostaController::executar(fn() => PedidoService::buscar((int) ($get['id_pedido'] ?? 0))); }
    public static function doCliente(int $idUsuario): array { return RespostaController::executar(fn() => PedidoService::doCliente($idUsuario)); }
    public static function historico(array $get): array { return RespostaController::executar(fn() => PedidoService::historico($get)); }
    public static function recentes(): array { return RespostaController::executar(fn() => PedidoService::recentes()); }

    public static function criarManual(array $e, ?int $idUsuario): array { return RespostaController::executar(fn() => PedidoService::criarManual($e, $idUsuario)); }
    public static function criarDoCliente(array $e, int $idUsuario): array { return RespostaController::executar(fn() => PedidoService::criarDoCliente($e, $idUsuario)); }

    public static function mudarStatus(array $e, ?int $idUsuario): array
    {
        return RespostaController::executar(fn() => PedidoService::mudarStatus(
            (int) ($e['id_pedido'] ?? 0), (string) ($e['status'] ?? ''), $idUsuario,
            isset($e['id_entregador']) && $e['id_entregador'] !== '' ? (int) $e['id_entregador'] : null
        ));
    }

    /** Tela da Cozinha / do Bar: {id_pedido, setor, etapa: iniciar|pronto} */
    public static function avancarSetor(array $e, ?int $idUsuario): array
    {
        return RespostaController::executar(fn() => PedidoService::avancarSetor(
            (int) ($e['id_pedido'] ?? 0), (string) ($e['setor'] ?? ''), (string) ($e['etapa'] ?? ''), $idUsuario
        ));
    }

    public static function atribuirEntregador(array $e): array
    {
        return RespostaController::executar(fn() => PedidoService::atribuirEntregador((int) ($e['id_pedido'] ?? 0), (int) ($e['id_entregador'] ?? 0)));
    }

    public static function pagar(array $e): array
    {
        return RespostaController::executar(fn() => PedidoService::pagar(
            (int) ($e['id_pedido'] ?? 0), is_array($e['pagamentos'] ?? null) ? $e['pagamentos'] : []
        ));
    }

    public static function fecharContaMesa(array $e, ?int $idUsuario): array
    {
        return RespostaController::executar(fn() => PedidoService::fecharContaMesa(
            (int) ($e['id_mesa'] ?? 0), is_array($e['pagamentos'] ?? null) ? $e['pagamentos'] : [], $idUsuario, !empty($e['com_taxa']), (int) ($e['id_garcom'] ?? 0) ?: null
        ));
    }

    public static function receberWebhook(array $payload): array { return RespostaController::executar(fn() => PedidoService::receberExterno($payload)); }

    /* mesas */
    public static function mesas(): array { return RespostaController::executar(fn() => MesaService::listar()); }
    public static function salvarMesa(array $e): array { return RespostaController::executar(fn() => MesaService::salvar($e)); }
    public static function excluirMesa(array $e): array { return RespostaController::executar(fn() => MesaService::excluir((int) ($e['id_mesa'] ?? 0))); }

    /* entregadores */
    public static function entregadores(): array { return RespostaController::executar(fn() => EntregadorService::listar()); }
    public static function salvarEntregador(array $e): array { return RespostaController::executar(fn() => EntregadorService::salvar($e)); }
    public static function excluirEntregador(array $e): array { return RespostaController::executar(fn() => EntregadorService::excluir((int) ($e['id_entregador'] ?? 0))); }

    public static function notificacoes(): array { return RespostaController::executar(fn() => NotificacaoService::recentes(30)); }
}
