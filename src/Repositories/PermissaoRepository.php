<?php

require_once __DIR__ . '/../../config/database.php';

/** USUARIO_PERMISSAO — o que cada funcionário pode fazer em cada tela. */
class PermissaoRepository
{
    /** @return array<string,array{ver:bool,incluir:bool,alterar:bool,excluir:bool}> */
    public static function doUsuario(int $idUsuario): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT modulo, pode_ver, pode_incluir, pode_alterar, pode_excluir FROM usuario_permissao WHERE id_usuario = :u'
        );
        $stmt->execute(['u' => $idUsuario]);
        $mapa = [];
        foreach ($stmt->fetchAll() as $l) {
            $mapa[$l['modulo']] = [
                'ver' => (bool) $l['pode_ver'], 'incluir' => (bool) $l['pode_incluir'],
                'alterar' => (bool) $l['pode_alterar'], 'excluir' => (bool) $l['pode_excluir'],
            ];
        }
        return $mapa;
    }

    /** Substitui todas as permissões do usuário (chamar dentro de transação). */
    public static function substituir(int $idUsuario, array $permissoes): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM usuario_permissao WHERE id_usuario = :u')->execute(['u' => $idUsuario]);
        $ins = $pdo->prepare(
            'INSERT INTO usuario_permissao (id_usuario, modulo, pode_ver, pode_incluir, pode_alterar, pode_excluir)
             VALUES (:u, :m, :v, :i, :a, :e)'
        );
        foreach ($permissoes as $modulo => $p) {
            $ins->execute(['u' => $idUsuario, 'm' => $modulo, 'v' => (int) $p['ver'], 'i' => (int) $p['incluir'],
                           'a' => (int) $p['alterar'], 'e' => (int) $p['excluir']]);
        }
    }

    public static function definirPerfil(int $idUsuario, string $perfil): void
    {
        Database::getConnection()->prepare("UPDATE usuario SET perfil = :p WHERE id_usuario = :u AND perfil <> 'master'")
            ->execute(['p' => $perfil, 'u' => $idUsuario]);
    }

    public static function listarFuncionarios(): array
    {
        return Database::getConnection()->query(
            "SELECT id_usuario, nome_completo, login, email, data_cadastro FROM usuario WHERE perfil = 'funcionario' ORDER BY nome_completo"
        )->fetchAll();
    }
}
