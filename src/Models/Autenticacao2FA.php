<?php

/**
 * Regras estáticas de apresentação do 2FA.
 * Persistência fica em Autenticacao2FARepository.
 */
class Autenticacao2FA
{
    public const PERGUNTAS = [
        'Qual o nome da sua mãe?',
        'Qual a data do seu nascimento?',
        'Qual o CEP do seu endereço?',
    ];
}
