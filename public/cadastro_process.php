<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/ValidationService.php';
require_once __DIR__ . '/../src/Services/PasswordService.php';
require_once __DIR__ . '/../src/Services/UsuarioService.php';
require_once __DIR__ . '/../src/Services/CadastroService.php';

SessionService::start();

// Cadastro de funcionário: só quando quem envia é o Master logado
$modoFuncionario = SessionService::ehMaster() && !empty($_POST['funcionario']);
$voltar = '/foodhub/public/cadastro.php' . ($modoFuncionario ? '?funcionario=1' : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $voltar);
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
} elseif ($dados['cpf'] !== '' && UsuarioService::cpfJaExiste($dados['cpf'])) {
    $erros[] = 'Este CPF já está cadastrado.';
}
if ($dados['email'] !== '' && !ValidationService::validarEmail($dados['email'])) {
    $erros[] = 'E-mail inválido.';
} elseif ($dados['email'] !== '' && UsuarioService::emailJaExiste($dados['email'])) {
    $erros[] = 'Este e-mail já está cadastrado.';
}
if ($dados['telefone_celular'] !== '' && !ValidationService::validarTelefone($dados['telefone_celular'])) {
    $erros[] = 'Telefone celular fora do formato (+55)XX-XXXXXXXXX.';
}
if ($dados['telefone_fixo'] !== '' && !ValidationService::validarTelefone($dados['telefone_fixo'])) {
    $erros[] = 'Telefone fixo fora do formato (+55)XX-XXXXXXXX.';
}
if ($dados['login'] !== '') {
    if (!ValidationService::validarLogin($dados['login'])) {
        $erros[] = 'Login deve ter exatamente 6 caracteres alfabéticos.';
    } elseif (UsuarioService::loginJaExiste($dados['login'])) {
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
    header('Location: ' . $voltar);
    exit;
}

// --- Tudo certo: usuário + 2FA são gravados em uma única transação ---
try {
    $idNovo = CadastroService::cadastrar($dados, $senha, $respostas2fa);
    if ($modoFuncionario) {
        require_once __DIR__ . '/../src/Services/PermissaoService.php';
        PermissaoService::salvar($idNovo, 'funcionario', []);   // começa sem telas; o Master marca em seguida
    }
} catch (Throwable $e) {
    SessionService::setFlash('cadastro_erros', [
        'Não foi possível concluir o cadastro. Tente novamente.'
    ]);
    SessionService::setFlash('cadastro_valores', $dados);
    header('Location: ' . $voltar);
    exit;
}

if ($modoFuncionario) {
    // Volta para Usuários com a grade de permissões do novo funcionário já aberta
    header('Location: /foodhub/public/consulta_usuario.php?permissoes=' . (int) $idNovo);
    exit;
}

SessionService::setFlash('login_mensagem', 'Cadastro realizado com sucesso! Faça login para continuar.');
header('Location: /foodhub/public/login.php');
exit;
