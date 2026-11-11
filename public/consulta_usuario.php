<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireMaster();

$termo    = trim($_GET['busca'] ?? '');
$usuarios = $termo !== '' ? UsuarioController::consultar($termo) : [];
$flash    = SessionService::getFlash('usuario_exclusao');

$layoutTitulo = 'Usuários';
$layoutAtivo  = 'consulta';
require __DIR__ . '/_layout.php';
?>

<div class="page-heading">
    <div>
        <h1>Usuários</h1>
        <p>Cadastre funcionários, defina o que cada um pode acessar e gerencie os clientes.</p>
    </div>
    <div class="acoes"><a href="cadastro.php?funcionario=1" class="btn-fh" style="text-decoration:none">+ Novo funcionário</a></div>
</div>

<!-- Funcionários e suas permissões -->
<div class="fh-card-inner" style="margin-bottom:1rem">
    <h2 style="margin-top:0">Funcionários</h2>
    <div class="table-wrap"><table class="fh-table">
        <thead><tr><th>Nome</th><th>Login</th><th>Telas liberadas</th><th style="text-align:center">Ações</th></tr></thead>
        <tbody id="tbFuncionarios"><tr><td colspan="4" class="vazio-msg">Carregando…</td></tr></tbody>
    </table></div>
    <p class="fh-hint">Um cliente já cadastrado também pode virar funcionário: pesquise o nome abaixo e clique em <strong>Permissões</strong>.</p>
</div>

<!-- Formulário de busca -->
<!-- Busca + resultados: o filtro roda via AJAX (RNF08) — assets/js/filtro-ajax.js troca só este bloco -->
<div id="areaConsulta">
<div class="fh-card-inner" style="padding-bottom:1.2rem;">
    <form method="get" action="consulta_usuario.php" data-ajax-filtro="areaConsulta" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="fh-form-group" style="flex:1;min-width:220px;margin:0;">
            <label for="busca">Pesquisar por nome (substring)</label>
            <input type="text" id="busca" name="busca" class="fh-input"
                   placeholder="Digite parte do nome…"
                   value="<?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?>"
                   autofocus>
        </div>
        <button type="submit" class="btn-fh" style="margin-bottom:0;">Buscar</button>
        <?php if ($termo !== ''): ?>
            <a href="consulta_usuario.php" data-ajax-filtro="areaConsulta" class="btn-fh-outline" style="text-decoration:none;display:inline-flex;align-items:center;">
                Limpar
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Resultados -->
<?php if ($termo !== ''): ?>
<div class="fh-card-inner" style="padding-top:1.2rem;">
    <?php if (empty($usuarios)): ?>
        <div style="text-align:center;padding:2rem;color:#888;">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:.75rem;opacity:.4;display:block;margin:0 auto 1rem;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <strong>Nenhum usuário encontrado</strong> para "<em><?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?></em>"
        </div>
    <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
            <p style="margin:0;color:#666;font-size:.9rem;">
                <?= count($usuarios) ?> resultado(s) para "<strong><?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?></strong>"
            </p>
            <a href="usuarios_pdf.php?busca=<?= urlencode($termo) ?>" target="_blank"
               class="btn-fh-outline"
               style="text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;font-size:.88rem;padding:.45rem 1rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Baixar PDF
            </a>
        </div>
        <div style="overflow-x:auto;">
            <table class="fh-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Login</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Cadastro</th>
                        <th style="text-align:center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['nome_completo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><code style="background:var(--cream);padding:.1rem .4rem;border-radius:4px;font-size:.85rem;"><?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="fh-badge <?= $u['perfil'] === 'funcionario' ? 'st-novo' : 'neutro' ?>"><?= $u['perfil'] === 'funcionario' ? 'Funcionário' : 'Cliente' ?></span></td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars(
                            date('d/m/Y H:i', strtotime($u['data_cadastro'])),
                            ENT_QUOTES, 'UTF-8'
                        ) ?></td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button type="button" class="btn-fh-outline btn-sm" data-permissoes="<?= (int)$u['id_usuario'] ?>">Permissões</button>
                            <button type="button"
                                class="btn-fh"
                                style="background:var(--red);font-size:.82rem;padding:.4rem .9rem;"
                                onclick="abrirModalExcluir(<?= (int)$u['id_usuario'] ?>, <?= htmlspecialchars(json_encode($u['nome_completo']), ENT_QUOTES, 'UTF-8') ?>)">
                                Excluir
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /areaConsulta -->

<!-- Perfil e permissões de um usuário -->
<div class="fh-overlay" id="modalPermissoes" role="dialog" aria-modal="true" aria-labelledby="tituloPerm">
    <div class="fh-modal largo">
        <h3 id="tituloPerm">Permissões</h3>
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="permPerfil">Perfil</label>
                <select class="fh-select" id="permPerfil">
                    <option value="funcionario">Funcionário (acessa as telas marcadas)</option>
                    <option value="comum">Cliente (só cardápio e os próprios pedidos)</option>
                </select></div>
        </div>
        <div id="blocoPerm">
            <p class="fh-label" style="margin-bottom:.3rem">Modelos prontos <span class="muted">(preenche a grade; depois ajuste à vontade)</span></p>
            <div id="permModelos" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.8rem"></div>
            <div class="table-wrap" style="max-height:340px;overflow-y:auto"><table class="fh-table grade-perm">
                <thead><tr><th>Tela</th><th>Ver</th><th>Incluir</th><th>Alterar</th><th>Excluir</th></tr></thead>
                <tbody id="tbPerm"></tbody>
            </table></div>
            <p class="fh-hint">Marcar Incluir, Alterar ou Excluir libera também o Ver. Usuários, Auditoria e Integrações são sempre exclusivos do Master.</p>
        </div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-ghost" id="permLimpar">Desmarcar tudo</button>
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalPermissoes">Cancelar</button>
            <button type="button" class="btn-fh" id="permSalvar">Salvar permissões</button>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="fh-overlay" id="overlayExcluir">
    <div class="fh-modal">
        <h3>Confirmar exclusão</h3>
        <p id="modalMsgExcluir">Deseja realmente excluir este usuário?</p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" onclick="fecharModal()">Cancelar</button>
            <form id="formExcluir" method="post" action="excluir_usuario.php" style="display:inline;">
                <input type="hidden" id="inputIdExcluir" name="id_usuario" value="">
                <input type="hidden" name="busca_retorno" value="<?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-fh">Confirmar exclusão</button>
            </form>
        </div>
    </div>
</div>

<?php if ($flash): ?>
<script>
    window.addEventListener('load', function () {
        var ok = <?= json_encode($flash['sucesso'] ?? false) ?>;
        fhToast(<?= json_encode($flash['mensagem'] ?? '') ?>, ok ? 'ok' : 'erro');
    });
</script>
<?php endif; ?>

<script>
function abrirModalExcluir(id, nome) {
    document.getElementById('inputIdExcluir').value = id;
    document.getElementById('modalMsgExcluir').textContent =
        'Deseja excluir o usuário "' + nome + '"? Esta ação não pode ser desfeita.';
    document.getElementById('overlayExcluir').classList.add('aberto');
}
function fecharModal() {
    document.getElementById('overlayExcluir').classList.remove('aberto');
}
document.getElementById('overlayExcluir').addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
});

/* Exportar PDF — abre em nova aba (já tratado pelo target="_blank") */
</script>

<?php
$layoutScripts = ['assets/js/filtro-ajax.js', 'assets/js/usuarios.js'];
require __DIR__ . '/_layout_fim.php';
?>
