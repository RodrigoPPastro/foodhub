<?php

/** RNF11 — contrato da sincronização de status com plataformas de delivery (iFood, Rappi…). */
interface PlataformaExternaAdapter
{
    /** @return bool true se a plataforma confirmou o novo status */
    public function sincronizarStatus(string $plataforma, string $idPedidoExterno, string $status): bool;
}
