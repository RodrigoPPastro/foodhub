<?php

require_once __DIR__ . '/SessionService.php';
require_once __DIR__ . '/../Models/Permissao.php';
require_once __DIR__ . '/../Repositories/PermissaoRepository.php';
require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/RegraNegocioException.php';

/**
 * Quem pode o quê.
 *   master      → tudo
 *   funcionario → só o que estiver marcado em USUARIO_PERMISSAO (lido do banco a cada requisição:
 *                 uma mudança feita pelo Master vale na hora, sem o funcionário sair do sistema)
 *   comum       → cliente do delivery: nenhuma tela de gestão
 */
class PermissaoService
{
    private static ?array $cache = null;
    private static ?int $cacheUsuario = null;

    public static function ehFuncionario(): bool
    {
        return SessionService::perfilAtual() === 'funcionario';
    }

    public static function ehCliente(): bool
    {
        return SessionService::perfilAtual() === 'comum';
    }

    /** Permissões do usuário logado (vazio para cliente; o Master não precisa consultar). */
    public static function atuais(): array
    {
        $id = SessionService::usuarioIdAtual();
        if (!$id || !self::ehFuncionario()) {
            return [];
        }
        if (self::$cache === null || self::$cacheUsuario !== $id) {
            self::$cache = PermissaoRepository::doUsuario((int) $id);
            self::$cacheUsuario = (int) $id;
        }
        return self::$cache;
    }

    public static function pode(string $modulo, string $acao = Permissao::VER): bool
    {
        if (SessionService::ehMaster()) {
            return true;
        }
        if (!self::ehFuncionario()) {
            return false;
        }
        return !empty(self::atuais()[$modulo][$acao]);
    }

    /** true se puder a ação em PELO MENOS um dos módulos. */
    public static function podeAlgum(array $modulos, string $acao = Permissao::VER): bool
    {
        foreach ($modulos as $m) {
            if (self::pode($m, $acao)) {
                return true;
            }
        }
        return false;
    }

    /** Primeira tela liberada (para onde o funcionário vai depois do login). */
    public static function paginaInicial(): ?string
    {
        foreach (Permissao::MODULOS as $modulo => $info) {
            if (self::pode($modulo)) {
                return $info['pagina'];
            }
        }
        return null;
    }

    /** Mapa do usuário logado para o navegador (só esconde botões; quem bloqueia é o servidor). */
    public static function paraNavegador(): array
    {
        $saida = [];
        foreach (Permissao::MODULOS as $modulo => $info) {
            foreach (Permissao::ACOES as $acao) {
                $saida[$modulo][$acao] = self::pode($modulo, $acao);
            }
        }
        return $saida;
    }

    public static function limparCache(): void
    {
        self::$cache = null;
        self::$cacheUsuario = null;
    }

    /* ══════════════ Gestão (somente Master) ══════════════ */

    public static function catalogo(): array
    {
        $modulos = [];
        foreach (Permissao::MODULOS as $codigo => $m) {
            $modulos[] = ['codigo' => $codigo, 'rotulo' => $m['rotulo'], 'grupo' => $m['grupo'], 'acoes' => $m['acoes'], 'dicas' => $m['dicas']];
        }
        $modelos = [];
        foreach (Permissao::MODELOS as $codigo => $m) {
            $modelos[] = ['codigo' => $codigo, 'rotulo' => $m['rotulo'], 'permissoes' => Permissao::doModelo($codigo)];
        }
        return ['modulos' => $modulos, 'modelos' => $modelos];
    }

    /** Perfil e permissões de um usuário (para a tela de Usuários). */
    public static function doUsuario(int $idUsuario): array
    {
        $u = UsuarioRepository::buscarPorId($idUsuario);
        if ($u === null) {
            throw new RegraNegocioException('Usuário não encontrado.');
        }
        if ($u['perfil'] === 'master') {
            throw new RegraNegocioException('O Master tem acesso total; as permissões dele não são editáveis.');
        }
        return [
            'id_usuario' => (int) $u['id_usuario'], 'nome' => $u['nome_completo'], 'login' => $u['login'],
            'perfil' => $u['perfil'], 'permissoes' => PermissaoRepository::doUsuario($idUsuario),
        ];
    }

    /**
     * Define o perfil (comum = cliente | funcionario) e as permissões.
     * @param array $permissoes modulo => [acao, acao, ...]  ou  modulo => {ver:bool, incluir:bool, ...}
     */
    public static function salvar(int $idUsuario, string $perfil, array $permissoes): array
    {
        if (!in_array($perfil, ['comum', 'funcionario'], true)) {
            throw new RegraNegocioException('Perfil inválido. Escolha Cliente ou Funcionário.');
        }
        $u = UsuarioRepository::buscarPorId($idUsuario);
        if ($u === null) {
            throw new RegraNegocioException('Usuário não encontrado.');
        }
        if ($u['perfil'] === 'master') {
            throw new RegraNegocioException('O Master tem acesso total; as permissões dele não são editáveis.');
        }

        $limpas = [];
        if ($perfil === 'funcionario') {
            foreach ($permissoes as $modulo => $acoes) {
                $modulo = (string) $modulo;
                if (!Permissao::existe($modulo)) {
                    throw new RegraNegocioException('Tela desconhecida nas permissões: ' . $modulo . '.');
                }
                if (is_array($acoes) && array_keys($acoes) === range(0, count($acoes) - 1)) {
                    $marcadas = array_map('strval', $acoes);
                } else {
                    $marcadas = array_keys(array_filter((array) $acoes, fn($v) => $v === true || $v === 1 || $v === '1'));
                }
                $linha = array_fill_keys(Permissao::ACOES, false);
                foreach ($marcadas as $acao) {
                    if (!Permissao::acaoValida($modulo, $acao)) {
                        throw new RegraNegocioException(sprintf('A ação "%s" não se aplica à tela %s.', $acao, Permissao::MODULOS[$modulo]['rotulo']));
                    }
                    $linha[$acao] = true;
                }
                // Quem pode incluir/alterar/excluir precisa, no mínimo, enxergar a tela
                if (in_array(true, $linha, true)) {
                    $linha[Permissao::VER] = true;
                    $limpas[$modulo] = $linha;
                }
            }
        }

        Transacao::executar(function () use ($idUsuario, $perfil, $limpas) {
            PermissaoRepository::definirPerfil($idUsuario, $perfil);
            PermissaoRepository::substituir($idUsuario, $limpas);
        });
        self::limparCache();
        return self::doUsuario($idUsuario);
    }

    public static function funcionarios(): array
    {
        $saida = [];
        foreach (PermissaoRepository::listarFuncionarios() as $f) {
            $perm = PermissaoRepository::doUsuario((int) $f['id_usuario']);
            $telas = [];
            foreach (Permissao::MODULOS as $codigo => $m) {
                if (!empty($perm[$codigo]['ver'])) {
                    $telas[] = $m['rotulo'];
                }
            }
            $saida[] = $f + ['telas' => $telas];
        }
        return $saida;
    }
}
