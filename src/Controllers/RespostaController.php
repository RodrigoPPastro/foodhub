<?php

require_once __DIR__ . '/../Services/RegraNegocioException.php';

/**
 * Padroniza o retorno dos Controllers do módulo financeiro:
 *   sucesso  -> ['http'=>200, 'sucesso'=>true,  'dados'=>...]
 *   regra    -> ['http'=>422, 'sucesso'=>false, 'mensagem'=>'(segura p/ toast)']
 *   erro     -> ['http'=>500, 'sucesso'=>false, 'mensagem'=>'genérica'] (detalhe só no log)
 */
class RespostaController
{
    public static function executar(callable $acao): array
    {
        try {
            return ['http' => 200, 'sucesso' => true, 'dados' => $acao()];
        } catch (RegraNegocioException $e) {
            return ['http' => 422, 'sucesso' => false, 'mensagem' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('[FoodHub][financeiro] ' . get_class($e) . ': ' . $e->getMessage());
            return [
                'http'     => 500,
                'sucesso'  => false,
                'mensagem' => 'Não foi possível processar a solicitação. Tente novamente em instantes.',
            ];
        }
    }
}
