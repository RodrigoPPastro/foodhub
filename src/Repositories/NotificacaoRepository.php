<?php

require_once __DIR__ . '/../../config/database.php';

/** NOTIFICACAO_CLIENTE — histórico das mensagens (simulação de WhatsApp). */
class NotificacaoRepository
{
    public static function inserir(int $idPedido, string $tipo, string $canal, string $mensagem, string $dataHora, string $status): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO notificacao_cliente (id_pedido, tipo, canal, mensagem, data_hora_envio, status_envio) VALUES (:p, :t, :c, :m, :d, :s)'
        )->execute(['p' => $idPedido, 't' => $tipo, 'c' => $canal, 'm' => $mensagem, 'd' => $dataHora, 's' => $status]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarRecentes(int $limite = 30): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id_notificacao, id_pedido, tipo, canal, mensagem, data_hora_envio, status_envio
             FROM notificacao_cliente ORDER BY data_hora_envio DESC, id_notificacao DESC LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function existe(int $idPedido, string $tipo): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM notificacao_cliente WHERE id_pedido = :p AND tipo = :t');
        $stmt->execute(['p' => $idPedido, 't' => $tipo]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
