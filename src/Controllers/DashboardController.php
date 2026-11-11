<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/DashboardService.php';

/** RF25 */
class DashboardController
{
    public static function indicadores(): array
    {
        return RespostaController::executar(fn() => DashboardService::indicadores());
    }
}
