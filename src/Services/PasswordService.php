<?php
/**
 * Centraliza hash/verificação de senha e das respostas do 2FA (RNF05).
 * Nunca armazenar nem comparar texto puro em lugar nenhum do sistema.
 */

class PasswordService
{
    public static function hash(string $textoPuro): string
    {
        return password_hash($textoPuro, PASSWORD_BCRYPT);
    }

    public static function verify(string $textoPuro, string $hash): bool
    {
        return password_verify($textoPuro, $hash);
    }

    /**
     * Normaliza a resposta do 2FA antes de gerar/comparar o hash
     * (evita falha por causa de espaço extra, maiúscula/minúscula etc.).
     */
    public static function normalizarResposta(string $resposta): string
    {
        return mb_strtolower(trim($resposta));
    }
}
