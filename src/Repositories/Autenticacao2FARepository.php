<?php

require_once __DIR__ . '/../../config/database.php';

class Autenticacao2FARepository
{
    public static function sortearPerguntaDoUsuario(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_2fa, pergunta FROM autenticacao_2fa WHERE id_usuario = :id'
        );
        $stmt->execute(['id' => $idUsuario]);
        $todas = $stmt->fetchAll();

        if (empty($todas)) {
            return null;
        }

        return $todas[array_rand($todas)];
    }

    public static function buscarPorIdEUsuario(int $id2fa, int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_2fa, id_usuario, pergunta, resposta_hash
             FROM autenticacao_2fa
             WHERE id_2fa = :id_2fa AND id_usuario = :id_usuario
             LIMIT 1'
        );
        $stmt->execute([
            'id_2fa' => $id2fa,
            'id_usuario' => $idUsuario,
        ]);
        $registro = $stmt->fetch();
        return $registro ?: null;
    }

    public static function salvarRespostas(int $idUsuario, array $perguntasComHash): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
             VALUES (:id_usuario, :pergunta, :resposta_hash)'
        );

        foreach ($perguntasComHash as $pergunta => $hash) {
            $stmt->execute([
                'id_usuario' => $idUsuario,
                'pergunta' => $pergunta,
                'resposta_hash' => $hash,
            ]);
        }
    }
}
