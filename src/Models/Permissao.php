<?php

/**
 * Telas (módulos) que um FUNCIONÁRIO pode receber e as ações de cada uma.
 * O Master sempre pode tudo; o cliente (perfil comum) não acessa nenhuma destas telas.
 * Usuários, Auditoria, Integrações e permissões são SEMPRE exclusivos do Master.
 */
class Permissao
{
    public const VER     = 'ver';
    public const INCLUIR = 'incluir';
    public const ALTERAR = 'alterar';
    public const EXCLUIR = 'excluir';
    public const ACOES   = [self::VER, self::INCLUIR, self::ALTERAR, self::EXCLUIR];

    /**
     * modulo => [rótulo, página, grupo, ações que fazem sentido, dica de cada ação]
     * A ordem define a ordem do menu e a "página inicial" do funcionário.
     */
    public const MODULOS = [
        'dashboard'    => ['rotulo' => 'Dashboard', 'pagina' => 'dashboard.php', 'grupo' => 'Visão geral',
                           'acoes' => ['ver'], 'dicas' => []],
        'pedidos'      => ['rotulo' => 'Pedidos', 'pagina' => 'pedidos.php', 'grupo' => 'Operação',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'],
                           'dicas' => ['incluir' => 'lançar pedido', 'alterar' => 'mudar status, entregador, receber pagamento', 'excluir' => 'cancelar pedido']],
        'cozinha'      => ['rotulo' => 'Cozinha', 'pagina' => 'cozinha.php', 'grupo' => 'Operação',
                           'acoes' => ['ver', 'alterar'], 'dicas' => ['alterar' => 'iniciar preparo e marcar pronto']],
        'bar'          => ['rotulo' => 'Bar', 'pagina' => 'bar.php', 'grupo' => 'Operação',
                           'acoes' => ['ver', 'alterar'], 'dicas' => ['alterar' => 'iniciar preparo e marcar pronto']],
        'mesas'        => ['rotulo' => 'Mesas', 'pagina' => 'mesas.php', 'grupo' => 'Operação',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'],
                           'dicas' => ['incluir' => 'cadastrar mesa', 'alterar' => 'editar mesa e fechar conta', 'excluir' => 'excluir mesa']],
        'entregadores' => ['rotulo' => 'Entregadores', 'pagina' => 'entregadores.php', 'grupo' => 'Operação',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'], 'dicas' => []],
        'produtos'     => ['rotulo' => 'Produtos', 'pagina' => 'produtos.php', 'grupo' => 'Cardápio e estoque',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'],
                           'dicas' => ['alterar' => 'editar, ativar/inativar, foto e ficha técnica']],
        'categorias'   => ['rotulo' => 'Categorias', 'pagina' => 'categorias.php', 'grupo' => 'Cardápio e estoque',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'], 'dicas' => []],
        'estoque'      => ['rotulo' => 'Estoque', 'pagina' => 'estoque.php', 'grupo' => 'Cardápio e estoque',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'],
                           'dicas' => ['incluir' => 'cadastrar insumo', 'alterar' => 'editar insumo e dar entrada/saída']],
        'caixa'        => ['rotulo' => 'Fechamento de caixa', 'pagina' => 'fechamento_caixa.php', 'grupo' => 'Gestão',
                           'acoes' => ['ver', 'incluir'], 'dicas' => ['incluir' => 'fechar o caixa do dia']],
        'contas'       => ['rotulo' => 'Contas a pagar/receber', 'pagina' => 'contas.php', 'grupo' => 'Gestão',
                           'acoes' => ['ver', 'incluir', 'alterar', 'excluir'],
                           'dicas' => ['alterar' => 'editar, dar baixa e estornar', 'excluir' => 'cancelar conta']],
        'relatorios'   => ['rotulo' => 'Relatórios', 'pagina' => 'relatorios.php', 'grupo' => 'Gestão',
                           'acoes' => ['ver'], 'dicas' => []],
    ];

    /** Modelos prontos: modulo => ações. "gerente" = tudo. */
    public const MODELOS = [
        'garcom'  => ['rotulo' => 'Garçom', 'permissoes' => [
            'pedidos' => ['ver', 'incluir', 'alterar'], 'mesas' => ['ver', 'alterar'],
        ]],
        'cozinha' => ['rotulo' => 'Cozinha', 'permissoes' => ['cozinha' => ['ver', 'alterar']]],
        'bar'     => ['rotulo' => 'Bar', 'permissoes' => ['bar' => ['ver', 'alterar']]],
        'caixa'   => ['rotulo' => 'Caixa', 'permissoes' => [
            'pedidos' => ['ver', 'alterar'], 'mesas' => ['ver', 'alterar'], 'caixa' => ['ver', 'incluir'],
        ]],
        'gerente' => ['rotulo' => 'Gerente', 'permissoes' => '*'],
    ];

    public static function existe(string $modulo): bool
    {
        return isset(self::MODULOS[$modulo]);
    }

    public static function acaoValida(string $modulo, string $acao): bool
    {
        return self::existe($modulo) && in_array($acao, self::MODULOS[$modulo]['acoes'], true);
    }

    /** Permissões completas de um modelo pronto: modulo => ações. */
    public static function doModelo(string $modelo): array
    {
        $p = self::MODELOS[$modelo]['permissoes'] ?? [];
        if ($p === '*') {
            return array_map(fn($m) => $m['acoes'], self::MODULOS);
        }
        return $p;
    }
}
