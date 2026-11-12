<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/GorjetaService.php';

/** Taxa de serviço dos garçons (Master). */
class GorjetaController
{
    public static function relatorio(array $get): array { return RespostaController::executar(fn() => GorjetaService::relatorio($get)); }
    public static function garcons(bool $soAtivos): array { return RespostaController::executar(fn() => GorjetaService::garcons($soAtivos)); }
    public static function salvarGarcom(array $e): array { return RespostaController::executar(fn() => GorjetaService::salvarGarcom($e)); }
    public static function pagar(array $e, ?int $u): array { return RespostaController::executar(fn() => GorjetaService::pagar($e, $u)); }
}
