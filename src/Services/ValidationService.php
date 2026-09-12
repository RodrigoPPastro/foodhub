<?php
/**
 * Todas as regras de validação do formulário de Cadastro (RF02),
 * isoladas aqui pra poderem ser testadas sem precisar de banco/sessão.
 */

class ValidationService
{
    /** Nome: 15 a 80 caracteres, só letras e espaços (aceita acentos). */
    public static function validarNome(string $nome): bool
    {
        $tamanho = mb_strlen(trim($nome));
        if ($tamanho < 15 || $tamanho > 80) {
            return false;
        }
        return (bool) preg_match('/^[\p{L}\s]+$/u', $nome);
    }

    /** CPF: 11 dígitos + verificação dos 2 dígitos verificadores. */
    public static function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }
        // Rejeita sequências tipo 111.111.111-11 (matematicamente "válidas" mas inválidas na prática)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
            }
            $digitoVerificador = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$posicao] !== $digitoVerificador) {
                return false;
            }
        }

        return true;
    }

    /** Telefone no formato (+55)XX-XXXXXXXX (fixo, 8 dígitos) ou (+55)XX-XXXXXXXXX (celular, 9 dígitos). */
    public static function validarTelefone(string $telefone): bool
    {
        return (bool) preg_match('/^\(\+55\)\d{2}-\d{8,9}$/', trim($telefone));
    }

    /** CEP no formato XXXXX-XXX. */
    public static function validarFormatoCep(string $cep): bool
    {
        return (bool) preg_match('/^\d{5}-?\d{3}$/', trim($cep));
    }

    /** Login: exatamente 6 caracteres alfabéticos. */
    public static function validarLogin(string $login): bool
    {
        return strlen($login) === 6 && ctype_alpha($login);
    }

    /** Senha: exatamente 8 caracteres alfabéticos (regra literal do enunciado). */
    public static function validarSenha(string $senha): bool
    {
        return strlen($senha) === 8 && ctype_alpha($senha);
    }

    public static function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
