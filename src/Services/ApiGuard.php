<?php

require_once __DIR__ . '/SessionService.php';

/**
 * Guarda para endpoints JSON (fetch/AJAX). Diferente do AuthGuard — que faz
 * redirect para uma página HTML —, aqui a resposta é 401/403 em JSON, que o
 * front-end transforma em toast. Não altera o AuthGuard do módulo de autenticação.
 */
class ApiGuard
{
    public static function requireMaster(): void
    {
        SessionService::start();

        if (!SessionService::estaAutenticado()) {
            self::responder(['http' => 401, 'sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
        }
        if (!SessionService::ehMaster()) {
            self::responder(['http' => 403, 'sucesso' => false, 'mensagem' => 'Acesso restrito ao perfil Master.']);
        }
    }

    /** @param string|string[] $metodos */
    public static function exigirMetodo($metodos): void
    {
        $permitidos = (array) $metodos;
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $permitidos, true)) {
            header('Allow: ' . implode(', ', $permitidos));
            self::responder(['http' => 405, 'sucesso' => false, 'mensagem' => 'Método não permitido.']);
        }
    }

    /**
     * Proteção CSRF para POST: exige Content-Type JSON + cabeçalho customizado.
     * Um formulário de outro site não consegue enviar nenhum dos dois sem
     * passar por preflight CORS (que este sistema não autoriza).
     */
    public static function exigirAjaxJson(): void
    {
        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        $ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        if (stripos($tipo, 'application/json') !== 0 || $ajax !== 'FoodHub') {
            self::responder(['http' => 400, 'sucesso' => false, 'mensagem' => 'Requisição inválida.']);
        }
    }

    public static function lerJson(): array
    {
        $dados = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($dados)) {
            self::responder(['http' => 400, 'sucesso' => false, 'mensagem' => 'Corpo da requisição inválido.']);
        }
        return $dados;
    }

    public static function responder(array $resultado): void
    {
        http_response_code($resultado['http'] ?? 200);
        unset($resultado['http']);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store'); // dado financeiro nunca em cache
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Entrega um PDF como download (nome já validado pelo Service). */
    public static function enviarPdf(string $conteudo, string $arquivo): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Content-Length: ' . strlen($conteudo));
        header('Cache-Control: no-store');
        echo $conteudo;
        exit;
    }
}
