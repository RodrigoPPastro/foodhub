<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * RF26 — leitura dos usuários para exportação. Repositório separado para não
 * mexer no UsuarioRepository do módulo de autenticação (evita conflito de merge).
 * NUNCA seleciona senha_hash nem dados sensíveis desnecessários.
 */
class UsuarioExportacaoRepository
{
    public static function listarTodos(): array
    {
        return Database::getConnection()->query(
            'SELECT nome_completo, login, email, telefone_celular, cpf, perfil, data_cadastro
             FROM usuario
             ORDER BY nome_completo'
        )->fetchAll();
    }
}
