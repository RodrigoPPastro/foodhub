<?php

require_once __DIR__ . '/../../config/database.php';

/** Executa um bloco em transação; se já houver uma aberta, apenas reaproveita. */
final class Transacao
{
    public static function executar(callable $acao)
    {
        $pdo = Database::getConnection();

        if ($pdo->inTransaction()) {
            return $acao();
        }

        $pdo->beginTransaction();
        try {
            $resultado = $acao();
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Sufixo para travar linhas (FOR UPDATE) apenas no MySQL/MariaDB. */
    public static function travar(): string
    {
        return Database::getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }
}
