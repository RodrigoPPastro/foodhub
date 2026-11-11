<?php

/**
 * Erro de validação/regra de negócio cuja mensagem é SEGURA para exibir ao
 * usuário (toast). Qualquer outra exceção é tratada como erro interno.
 */
class RegraNegocioException extends RuntimeException
{
}
