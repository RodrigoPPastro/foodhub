<?php

require_once __DIR__ . '/../Repositories/NotificacaoRepository.php';
require_once __DIR__ . '/../Adapters/Adapters.php';
require_once __DIR__ . '/../Models/CanalVenda.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/Dinheiro.php';

/**
 * Mensagens ao cliente (confirmação, atraso, pronto). O envio real fica atrás de
 * Adapters::whatsapp() (RNF11) — hoje é simulado e só é registrado em notificacao_cliente.
 */
class NotificacaoService
{
    /**
     * Envia e registra. NUNCA lança exceção: falha de notificação não pode derrubar o pedido.
     * @param array $pedido linha de PedidoRepository::buscar() (com usuario_nome/usuario_telefone)
     */
    public static function enviar(array $pedido, string $tipo): ?array
    {
        try {
            $telefone = trim((string) (($pedido['telefone_cliente'] ?? '') ?: ($pedido['usuario_telefone'] ?? '')));
            if ($telefone === '') {
                return null;
            }
            $nome = trim((string) (($pedido['nome_cliente'] ?? '') ?: ($pedido['usuario_nome'] ?? '')));
            $mensagem = self::montar($pedido, $tipo, $nome);

            try {
                $status = Adapters::whatsapp()->enviar($telefone, $mensagem);
            } catch (Throwable $e) {
                error_log('[FoodHub] Falha no adapter WhatsApp: ' . $e->getMessage());
                $status = 'falha';
            }
            $agora = PeriodoService::agora()->format('Y-m-d H:i:s');
            $id = NotificacaoRepository::inserir((int) $pedido['id_pedido'], $tipo, 'whatsapp', $mensagem, $agora, $status);
            return ['id_notificacao' => $id, 'tipo' => $tipo, 'status_envio' => $status, 'mensagem' => $mensagem];
        } catch (Throwable $e) {
            error_log('[FoodHub] Notificação não registrada: ' . $e->getMessage());
            return null;
        }
    }

    public static function recentes(int $limite = 30): array
    {
        return NotificacaoRepository::listarRecentes(max(1, min($limite, 100)));
    }

    private static function montar(array $p, string $tipo, string $nome): string
    {
        $saudacao = $nome !== '' ? 'Olá, ' . explode(' ', $nome)[0] . '!' : 'Olá!';
        $id = (int) $p['id_pedido'];
        return match ($tipo) {
            'confirmacao' => sprintf('%s Recebemos o seu pedido #%d no FOOD HUB. Total: %s. Avisaremos quando ele estiver pronto.',
                $saudacao, $id, Dinheiro::formatar(Dinheiro::centavos($p['valor_total']))),
            'atraso' => sprintf('%s O seu pedido #%d está levando um pouco mais que o previsto. Pedimos desculpas — já estamos finalizando!', $saudacao, $id),
            'pronto' => CanalVenda::ehPresencial((string) $p['canal'])
                ? sprintf('%s O seu pedido #%d está pronto! Pode retirar.', $saudacao, $id)
                : sprintf('%s O seu pedido #%d está pronto e logo sairá para entrega.', $saudacao, $id),
            default => throw new InvalidArgumentException('Tipo de notificação inválido.'),
        };
    }
}
