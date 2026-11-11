<?php

require_once __DIR__ . '/PlataformaExternaAdapter.php';

/** Simulação: finge que a plataforma aceitou a atualização de status. */
class PlataformaExternaSimuladaAdapter implements PlataformaExternaAdapter
{
    public function sincronizarStatus(string $plataforma, string $idPedidoExterno, string $status): bool
    {
        error_log(sprintf('[FoodHub][%s simulado] pedido %s -> %s', $plataforma, $idPedidoExterno, $status));
        return true;
    }
}
