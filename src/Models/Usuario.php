<?php
require_once __DIR__ . '/../../config/database.php';

class Usuario
{
    public static function buscarPorLogin(string $login): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM usuario WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    public static function buscarPorId(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = :id LIMIT 1');
        $stmt->execute(['id' => $idUsuario]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /**
     * RF06 — Consulta de Usuários: busca por substring do nome (só usuários comuns).
     */
    public static function buscarPorNomeSubstring(string $trecho): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id_usuario, nome_completo, login, email, data_cadastro
             FROM usuario
             WHERE perfil = 'comum' AND nome_completo LIKE :trecho
             ORDER BY nome_completo"
        );
        $stmt->execute(['trecho' => '%' . $trecho . '%']);
        return $stmt->fetchAll();
    }

    /**
     * RF06 — Exclusão de usuário (somente master pode chamar; ver AuthGuard::requireMaster()).
     */
    public static function excluir(int $idUsuario): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id AND perfil = 'comum'");
        return $stmt->execute(['id' => $idUsuario]);
    }

    /**
     * RF07 — Alteração de senha (o próprio usuário comum altera a própria senha).
     */
    public static function atualizarSenha(int $idUsuario, string $novoHash): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE usuario SET senha_hash = :hash WHERE id_usuario = :id');
        return $stmt->execute(['hash' => $novoHash, 'id' => $idUsuario]);
    }

    public static function loginJaExiste(string $login): bool
    {
        return self::buscarPorLogin($login) !== null;
    }

    public static function cpfJaExiste(string $cpf): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM usuario WHERE cpf = :cpf LIMIT 1');
        $stmt->execute(['cpf' => $cpf]);
        return (bool) $stmt->fetchColumn();
    }

    public static function emailJaExiste(string $email): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * RF02 — cria um novo usuário comum. Retorna o id_usuario criado.
     */
    public static function criar(array $dados): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO usuario
                (nome_completo, data_nascimento, sexo, nome_materno, cpf, email,
                 telefone_celular, telefone_fixo, endereco_completo, login, senha_hash, perfil)
             VALUES
                (:nome_completo, :data_nascimento, :sexo, :nome_materno, :cpf, :email,
                 :telefone_celular, :telefone_fixo, :endereco_completo, :login, :senha_hash, \'comum\')'
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
