<?php
require_once __DIR__ . '/../../config/database.php';

class Autenticacao2FA
{
    public const PERGUNTAS = [
        'Qual o nome da sua mãe?',
        'Qual a data do seu nascimento?',
        'Qual o CEP do seu endereço?',
    ];

    /**
     * Sorteia UMA pergunta entre as cadastradas para o usuário (RF05).
     * Retorna ['pergunta' => string, 'resposta_hash' => string] ou null se
     * o usuário ainda não tiver 2FA cadastrado (falha de dados / cadastro incompleto).
     */
    public static function sortearPerguntaDoUsuario(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT pergunta, resposta_hash FROM autenticacao_2fa WHERE id_usuario = :id'
        );
        $stmt->execute(['id' => $idUsuario]);
        $todas = $stmt->fetchAll();

        if (empty($todas)) {
            return null;
        }

        return $todas[array_rand($todas)];
    }

    public static function salvarRespostas(int $idUsuario, array $perguntasComResposta): void
    {
        // $perguntasComResposta = ['Qual o nome da sua mãe?' => 'Maria', ...]
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
             VALUES (:id_usuario, :pergunta, :resposta_hash)'
        );

        foreach ($perguntasComResposta as $pergunta => $respostaPlana) {
            $normalizada = PasswordService::normalizarResposta($respostaPlana);
            $stmt->execute([
                'id_usuario'    => $idUsuario,
                'pergunta'      => $pergunta,
                'resposta_hash' => PasswordService::hash($normalizada),
            ]);
        }
    }
}
