<?php
session_start();

/**
 * Teste automatizado do fluxo de autenticação, usando SQLite em memória
 * no lugar do MySQL (não precisa de XAMPP rodando pra usar este arquivo).
 *
 * Rodar com:  php tests/smoke_test.php
 */

if (!class_exists('Database')) {
class Database
{
    private static ?PDO $instance = null;
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO('sqlite::memory:');
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec("
                CREATE TABLE usuario (
                    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
                    nome_completo TEXT, data_nascimento TEXT, sexo TEXT, nome_materno TEXT,
                    cpf TEXT, email TEXT, telefone_celular TEXT, telefone_fixo TEXT,
                    endereco_completo TEXT, login TEXT, senha_hash TEXT,
                    perfil TEXT DEFAULT 'comum', data_cadastro TEXT DEFAULT CURRENT_TIMESTAMP
                );
            ");
            self::$instance->exec("
                CREATE TABLE autenticacao_2fa (
                    id_2fa INTEGER PRIMARY KEY AUTOINCREMENT,
                    id_usuario INTEGER, pergunta TEXT, resposta_hash TEXT
                );
            ");
            self::$instance->exec("
                CREATE TABLE log_acesso (
                    id_log INTEGER PRIMARY KEY AUTOINCREMENT,
                    id_usuario INTEGER, data_hora TEXT DEFAULT CURRENT_TIMESTAMP,
                    tipo_2fa_utilizado TEXT, resultado TEXT
                );
            ");
        }
        return self::$instance;
    }
}
}

require_once __DIR__ . '/../src/Services/PasswordService.php';
require_once __DIR__ . '/../src/Services/ValidationService.php';
require_once __DIR__ . '/../src/Models/Usuario.php';
require_once __DIR__ . '/../src/Models/Autenticacao2FA.php';
require_once __DIR__ . '/../src/Models/LogAcesso.php';
require_once __DIR__ . '/../src/Services/TwoFactorService.php';

$falhou = false;
function check($cond, $label) {
    echo ($cond ? "OK  " : "FAIL") . " - $label\n";
    if (!$cond) { $GLOBALS['falhou'] = true; }
}

// Validações (RF02)
check(ValidationService::validarCPF('529.982.247-25'), 'CPF válido é aceito');
check(!ValidationService::validarCPF('111.111.111-11'), 'CPF repetido é rejeitado');
check(ValidationService::validarLogin('mariax'), 'Login de 6 letras é aceito');
check(ValidationService::validarSenha('abcdefgh'), 'Senha de 8 letras é aceita');

// Cadastro + hash
$pdo = Database::getConnection();
$stmt = $pdo->prepare("INSERT INTO usuario (nome_completo, login, senha_hash, perfil) VALUES (?, ?, ?, ?)");
$stmt->execute(['Maria Souza', 'mariax', PasswordService::hash('senha123'), 'comum']);
$idUsuario = (int) $pdo->lastInsertId();

Autenticacao2FA::salvarRespostas($idUsuario, [
    'Qual o nome da sua mãe?' => 'Joana',
    'Qual a data do seu nascimento?' => '01/01/2000',
    'Qual o CEP do seu endereço?' => '20000-000',
]);

$usuario = Usuario::buscarPorLogin('mariax');
check($usuario !== null, 'Usuário encontrado por login');
check(PasswordService::verify('senha123', $usuario['senha_hash']), 'Senha correta é validada');

// Fluxo de 2FA completo
$pergunta = TwoFactorService::iniciar($usuario);
check(in_array($pergunta, Autenticacao2FA::PERGUNTAS), 'Pergunta sorteada é válida');

$mapa = ['Qual o nome da sua mãe?' => 'Joana',
         'Qual a data do seu nascimento?' => '01/01/2000',
         'Qual o CEP do seu endereço?' => '20000-000'];
$resultado = TwoFactorService::validarResposta($mapa[$pergunta]);
check($resultado['status'] === 'sucesso', '2FA aceita resposta correta');

// Bloqueio após 3 tentativas
unset($_SESSION['2fa_pendente']);
TwoFactorService::iniciar($usuario);
TwoFactorService::validarResposta('errada1');
TwoFactorService::validarResposta('errada2');
$r3 = TwoFactorService::validarResposta('errada3');
check($r3['status'] === 'bloqueado', '3ª tentativa errada bloqueia o 2FA');

$logs = LogAcesso::listar('Maria', null);
check(count($logs) === 2, 'Log registrou sucesso e bloqueio');

echo $falhou ? "\nALGUM TESTE FALHOU.\n" : "\nTODOS OS TESTES PASSARAM.\n";
exit($falhou ? 1 : 0);
