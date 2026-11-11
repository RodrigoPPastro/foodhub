<?php
/**
 * Conexão central do FOOD HUB.
 * Credenciais locais ficam em config/database.local.php.
 */

if (!class_exists('Database')) {
    class Database
    {
        private static ?PDO $instance = null;

        private static function getConfig(): array
        {
            $config = [
                'host'    => getenv('FOODHUB_DB_HOST') ?: '127.0.0.1',
                'port'    => getenv('FOODHUB_DB_PORT') ?: '3306',
                'dbname'  => getenv('FOODHUB_DB_NAME') ?: 'foodhub',
                'user'    => getenv('FOODHUB_DB_USER') ?: 'root',
                'pass'    => getenv('FOODHUB_DB_PASS') ?: '',
                'charset' => 'utf8mb4',
            ];

            $localFile = __DIR__ . '/database.local.php';

            if (is_file($localFile)) {
                $localConfig = require $localFile;

                if (!is_array($localConfig)) {
                    throw new RuntimeException(
                        'A configuração local do banco deve retornar um array.'
                    );
                }

                $config = array_replace($config, $localConfig);
            }

            return $config;
        }

        public static function getConnection(): PDO
        {
            if (self::$instance === null) {
                $config = self::getConfig();

                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['dbname'],
                    $config['charset']
                );

                try {
                    self::$instance = new PDO(
                        $dsn,
                        $config['user'],
                        $config['pass'],
                        [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]
                    );
                } catch (PDOException $e) {
                    error_log('Erro de conexão com o banco: ' . $e->getMessage());

                    http_response_code(500);
                    exit('Não foi possível conectar ao banco de dados. Verifique a configuração local.');
                }
            }

            return self::$instance;
        }
    }
}