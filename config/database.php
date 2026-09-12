<?php
/**
 * Conexão única com o banco (PDO), compartilhada por todos os módulos.
 * Ajuste as credenciais abaixo (ou melhor: mova para variáveis de ambiente
 * antes de subir pro repositório — nunca commitar senha real).
 */

if (!class_exists('Database')) {
class Database
{
    private static ?PDO $instance = null;

    private const HOST    = '127.0.0.1';
    private const DBNAME  = 'foodhub';
    private const USER    = 'root';
    private const PASS    = '';
    private const CHARSET = 'utf8mb4';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::HOST,
                self::DBNAME,
                self::CHARSET
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    self::USER,
                    self::PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // Em produção, nunca exponha $e->getMessage() na tela (RF04 / segurança).
                error_log('Erro de conexão com o banco: ' . $e->getMessage());
                header('Location: /foodhub/public/erro.php?tipo=conexao');
                exit;
            }
        }

        return self::$instance;
    }
}
} // fim do class_exists guard (permite substituir por um stub em testes automatizados)
