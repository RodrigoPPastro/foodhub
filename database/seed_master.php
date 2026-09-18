<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/PasswordService.php';
require_once __DIR__ . '/../src/Repositories/Autenticacao2FARepository.php';

$pdo = Database::getConnection();

$login = 'MASTER';
$senha = 'Master1!';

$nome = 'Administrador Master';
$dataNascimento = '1990-01-01';
$sexo = 'Não informado';
$nomeMaterno = 'Maria';
$cpf = '00000000000';
$email = 'master@foodhub.com';
$telefoneCelular = '(21) 999999999';
$endereco = 'Sede FOOD HUB';

try {
    $pdo->beginTransaction();

    $senhaHash = PasswordService::hash($senha);

    $stmt = $pdo->prepare(
        'INSERT INTO usuario
        (
            nome_completo,
            data_nascimento,
            sexo,
            nome_materno,
            cpf,
            email,
            telefone_celular,
            telefone_fixo,
            endereco_completo,
            login,
            senha_hash,
            perfil
        )
        VALUES
        (
            :nome,
            :data_nascimento,
            :sexo,
            :nome_materno,
            :cpf,
            :email,
            :telefone_celular,
            NULL,
            :endereco,
            :login,
            :senha_hash,
            :perfil
        )'
    );

    $stmt->execute([
        'nome' => $nome,
        'data_nascimento' => $dataNascimento,
        'sexo' => $sexo,
        'nome_materno' => $nomeMaterno,
        'cpf' => $cpf,
        'email' => $email,
        'telefone_celular' => $telefoneCelular,
        'endereco' => $endereco,
        'login' => $login,
        'senha_hash' => $senhaHash,
        'perfil' => 'master'
    ]);

    $idUsuario = (int) $pdo->lastInsertId();

    // O Repository só grava — quem faz o hash é sempre a camada de serviço
    // (o mesmo caminho que o CadastroService usa no cadastro normal).
    $respostasComHash = [
        'Qual o nome da sua mãe?' => PasswordService::hash(
            PasswordService::normalizarResposta('Maria')
        ),
        'Qual a data do seu nascimento?' => PasswordService::hash(
            PasswordService::normalizarResposta('01/01/1990')
        ),
        'Qual o CEP do seu endereço?' => PasswordService::hash(
            PasswordService::normalizarResposta('20000000')
        ),
    ];

    Autenticacao2FARepository::salvarRespostas($idUsuario, $respostasComHash);

    $pdo->commit();

    echo "Usuário master criado com sucesso!" . PHP_EOL;
    echo "Login: {$login}" . PHP_EOL;
    echo "Senha: {$senha}" . PHP_EOL;
    echo "Respostas do 2FA cadastradas: Maria / 01/01/1990 / 20000000" . PHP_EOL;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "Erro ao criar usuário master:" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
}