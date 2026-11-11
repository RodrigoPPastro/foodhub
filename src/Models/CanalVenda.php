<?php

/**
 * Canais de venda (PEDIDO.canal). RNF09: vêm da tabela canal_venda — nova plataforma = um INSERT.
 * Os valores abaixo são o fallback caso a tabela não esteja disponível.
 */
class CanalVenda
{
    /** Canais presenciais; todo o resto é considerado "delivery". */
    public const SALAO = ['balcao', 'mesa'];

    private const ROTULOS = [
        'balcao' => 'Balcão', 'mesa' => 'Mesa', 'delivery_proprio' => 'Delivery próprio', 'ifood' => 'iFood', 'rappi' => 'Rappi',
    ];

    private static ?array $cache = null;

    private static function tabela(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                require_once __DIR__ . '/../../config/database.php';
                foreach (Database::getConnection()->query('SELECT codigo, rotulo, presencial, externo FROM canal_venda')->fetchAll() as $r) {
                    self::$cache[$r['codigo']] = ['rotulo' => $r['rotulo'], 'presencial' => (bool) $r['presencial'], 'externo' => (bool) $r['externo']];
                }
            } catch (Throwable $e) {
                // tabela ausente (ex.: testes com banco reduzido): usa o fallback
            }
        }
        return self::$cache;
    }

    public static function rotulo(string $canal): string
    {
        return self::tabela()[$canal]['rotulo'] ?? self::ROTULOS[$canal] ?? ucfirst(str_replace('_', ' ', $canal));
    }

    public static function ehPresencial(string $canal): bool
    {
        return self::tabela()[$canal]['presencial'] ?? in_array($canal, self::SALAO, true);
    }

    public static function ehDelivery(string $canal): bool
    {
        return !self::ehPresencial($canal);
    }

    public static function ehExterno(string $canal): bool
    {
        return self::tabela()[$canal]['externo'] ?? in_array($canal, ['ifood', 'rappi'], true);
    }

    /** Canais ativos: [codigo => rotulo]. */
    public static function ativos(): array
    {
        $ativos = [];
        try {
            require_once __DIR__ . '/../../config/database.php';
            foreach (Database::getConnection()->query('SELECT codigo, rotulo FROM canal_venda WHERE ativo = 1 ORDER BY presencial DESC, rotulo')->fetchAll() as $r) {
                $ativos[$r['codigo']] = $r['rotulo'];
            }
        } catch (Throwable $e) {
            $ativos = self::ROTULOS;
        }
        return $ativos;
    }
}
