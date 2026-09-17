<?php

require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/../Repositories/Autenticacao2FARepository.php';
require_once __DIR__ . '/PasswordService.php';

class CadastroService
{
    /**
     * RF02 — grava usuário e suas respostas 2FA como uma única operação.
     * Se qualquer etapa falhar, nada fica gravado pela transação.
     */
    public static function cadastrar(array $dados, string $senha, array $respostas2fa): int
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $dados['senha_hash'] = PasswordService::hash($senha);
            $idUsuario = UsuarioRepository::criar($dados);

            $perguntas = [
                'Qual o nome da sua mãe?',
                'Qual a data do seu nascimento?',
                'Qual o CEP do seu endereço?',
            ];
            $perguntasComHash = [];

            foreach ($perguntas as $i => $pergunta) {
                $normalizada = PasswordService::normalizarResposta($respostas2fa[$i] ?? '');
                $perguntasComHash[$pergunta] = PasswordService::hash($normalizada);
            }

            Autenticacao2FARepository::salvarRespostas($idUsuario, $perguntasComHash);

            $pdo->commit();
            return $idUsuario;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro no cadastro do usuário: ' . $e->getMessage());
            throw $e;
        }
    }
}
