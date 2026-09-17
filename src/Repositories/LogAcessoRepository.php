<?php

require_once __DIR__ . '/../../config/database.php';

class LogAcessoRepository
{
    public static function registrar(int $idUsuario, string $tipo2fa, string $resultado): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO log_acesso (id_usuario, tipo_2fa_utilizado, resultado)
             VALUES (:id_usuario, :tipo_2fa, :resultado)'
        );
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'tipo_2fa' => $tipo2fa,
            'resultado' => $resultado,
        ]);
    }

    public static function listar(?string $nome = null, ?string $cpf = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT l.data_hora, u.nome_completo, u.cpf,
                       l.tipo_2fa_utilizado, l.resultado
                FROM log_acesso l
                INNER JOIN usuario u ON u.id_usuario = l.id_usuario
                WHERE 1 = 1';
        $params = [];

        if (!empty($nome)) {
            $sql .= ' AND u.nome_completo LIKE :nome';
            $params['nome'] = '%' . $nome . '%';
        }
        if (!empty($cpf)) {
            $sql .= ' AND u.cpf = :cpf';
            $params['cpf'] = $cpf;
        }

        $sql .= ' ORDER BY l.data_hora DESC, l.id_log DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
