<?php

require_once __DIR__ . '/../../config/database.php';

class UsuarioRepository
{
    public static function buscarPorLogin(string $login): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM usuario WHERE login = :login LIMIT 1'
        );
        $stmt->execute(['login' => $login]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    public static function buscarPorId(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $idUsuario]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /**
     * RF06 — consulta clientes e funcionários (nunca o Master) por trecho do nome.
     */
    public static function buscarPorNomeSubstring(string $trecho): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id_usuario, nome_completo, login, email, data_cadastro, perfil
             FROM usuario
             WHERE perfil IN ('comum', 'funcionario') AND nome_completo LIKE :trecho
             ORDER BY nome_completo"
        );
        $stmt->execute(['trecho' => '%' . $trecho . '%']);
        return $stmt->fetchAll();
    }

    /**
     * RF06 — exclusão de cliente ou funcionário (o Master nunca é excluído).
     */
    public static function excluir(int $idUsuario): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "DELETE FROM usuario
             WHERE id_usuario = :id AND perfil IN ('comum', 'funcionario')"
        );
        $stmt->execute(['id' => $idUsuario]);
        return $stmt->rowCount() > 0;
    }

    /**
     * RF07 — atualiza a senha do usuário informado.
     */
    public static function atualizarSenha(int $idUsuario, string $novoHash): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE usuario SET senha_hash = :hash WHERE id_usuario = :id'
        );
        $stmt->execute([
            'hash' => $novoHash,
            'id' => $idUsuario
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function loginJaExiste(string $login): bool
    {
        return self::buscarPorLogin($login) !== null;
    }

    public static function cpfJaExiste(string $cpf): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM usuario WHERE cpf = :cpf LIMIT 1'
        );
        $stmt->execute(['cpf' => $cpf]);
        return (bool) $stmt->fetchColumn();
    }

    public static function emailJaExiste(string $email): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM usuario WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * RF02 — cria usuário comum e retorna o id.
     */
    public static function criar(array $dados): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO usuario
                (nome_completo, data_nascimento, sexo, nome_materno, cpf, email,
                 telefone_celular, telefone_fixo, endereco_completo, login, senha_hash, perfil)
             VALUES
                (:nome_completo, :data_nascimento, :sexo, :nome_materno, :cpf, :email,
                 :telefone_celular, :telefone_fixo, :endereco_completo, :login, :senha_hash, 'comum')"
        );
        $stmt->execute([
            'nome_completo'     => $dados['nome_completo'],
            'data_nascimento'   => $dados['data_nascimento'],
            'sexo'              => $dados['sexo'],
            'nome_materno'      => $dados['nome_materno'],
            'cpf'               => $dados['cpf'],
            'email'             => $dados['email'],
            'telefone_celular'  => $dados['telefone_celular'],
            'telefone_fixo'     => $dados['telefone_fixo'] ?? null,
            'endereco_completo' => $dados['endereco_completo'],
            'login'             => $dados['login'],
            'senha_hash'        => $dados['senha_hash'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}
