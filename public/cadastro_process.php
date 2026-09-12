<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/ValidationService.php';
require_once __DIR__ . '/../src/Services/PasswordService.php';
require_once __DIR__ . '/../src/Models/Usuario.php';
require_once __DIR__ . '/../src/Models/Autenticacao2FA.php';

SessionService::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodhub/public/cadastro.php');
    exit;
}

$dados = [
    'nome_completo'     => trim($_POST['nome_completo'] ?? ''),
    'data_nascimento'   => trim($_POST['data_nascimento'] ?? ''),
    'sexo'              => trim($_POST['sexo'] ?? ''),
    'nome_materno'      => trim($_POST['nome_materno'] ?? ''),
    'cpf'               => preg_replace('/\D/', '', $_POST['cpf'] ?? ''),
    'email'             => trim($_POST['email'] ?? ''),
    'telefone_celular'  => trim($_POST['telefone_celular'] ?? ''),
    'telefone_fixo'     => trim($_POST['telefone_fixo'] ?? ''),
    'cep'               => trim($_POST['cep'] ?? ''),
    'endereco_completo' => trim($_POST['endereco_completo'] ?? ''),
    'login'             => trim($_POST['login'] ?? ''),
];
$senha           = $_POST['senha'] ?? '';
$confirmarSenha  = $_POST['confirmar_senha'] ?? '';
$respostas2fa    = $_POST['resposta_2fa'] ?? [];

$erros = [];

// --- Campos obrigatórios ---
$obrigatorios = ['nome_completo','data_nascimento','sexo','nome_materno','cpf',
                  'email','telefone_celular','endereco_completo','login'];
foreach ($obrigatorios as $campo) {
    if ($dados[$campo] === '') {
        $erros[] = "O campo '$campo' é obrigatório.";
    }
}

// --- Regras específicas (só roda se o campo não estiver vazio, pra não duplicar erro) ---
if ($dados['nome_completo'] !== '' && !ValidationService::validarNome($dados['nome_completo'])) {
    $erros[] = 'Nome deve ter entre 15 e 80 caracteres alfabéticos.';
}
if ($dados['cpf'] !== '' && !ValidationService::validarCPF($dados['cpf'])) {
    $erros[] = 'CPF inválido (dígito verificador não confere).';
} elseif ($dados['cpf'] !== '' && Usuario::cpfJaExiste($dados['cpf'])) {
    $erros[] = 'Este CPF já está cadastrado.';
}
if ($dados['email'] !== '' && !ValidationService::validarEmail($dados['email'])) {
    $erros[] = 'E-mail inválido.';
} elseif ($dados['email'] !== '' && Usuario::emailJaExiste($dados['email'])) {
    $erros[] = 'Este e-mail já está cadastrado.';
}
if ($dados['telefone_celular'] !== '' && !ValidationService::validarTelefone($dados['telefone_celular'])) {
    $erros[] = 'Telefone celular fora do formato XX-XXXXXXXXX.';
}
if ($dados['telefone_fixo'] !== '' && !ValidationService::validarTelefone($dados['telefone_fixo'])) {
    $erros[] = 'Telefone fixo fora do formato XX-XXXXXXXX.';
}
if ($dados['login'] !== '') {
    if (!ValidationService::validarLogin($dados['login'])) {
        $erros[] = 'Login deve ter exatamente 6 caracteres alfabéticos.';
    } elseif (Usuario::loginJaExiste($dados['login'])) {
        $erros[] = 'Este login já está em uso.';
    }
}
if (!ValidationService::validarSenha($senha)) {
    $erros[] = 'Senha deve ter exatamente 8 caracteres alfabéticos.';
} elseif ($senha !== $confirmarSenha) {
    $erros[] = 'A confirmação de senha não confere.';
}
if (count(array_filter($respostas2fa, fn($r) => trim($r) !== '')) < 3) {
    $erros[] = 'As 3 perguntas de segurança devem ser respondidas.';
}

// --- Se deu erro, volta pro formulário preservando o que já foi digitado (exceto senha) ---
if (!empty($erros)) {
    SessionService::setFlash('cadastro_erros', $erros);
    SessionService::setFlash('cadastro_valores', $dados);
    header('Location: /foodhub/public/cadastro.php');
    exit;
}

// --- Tudo certo: grava usuário + hash da senha + respostas do 2FA ---
$dados['senha_hash'] = PasswordService::hash($senha);
$idUsuario = Usuario::criar($dados);

$perguntas = Autenticacao2FA::PERGUNTAS;
$mapaPerguntaResposta = [];
foreach ($perguntas as $i => $pergunta) {
    $mapaPerguntaResposta[$pergunta] = $respostas2fa[$i];
}
Autenticacao2FA::salvarRespostas($idUsuario, $mapaPerguntaResposta);

SessionService::setFlash('login_mensagem', 'Cadastro realizado com sucesso! Faça login para continuar.');
header('Location: /foodhub/public/login.php');
exit;
