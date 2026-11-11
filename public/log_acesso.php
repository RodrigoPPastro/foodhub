<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/LogAcessoService.php';

AuthGuard::requireMaster();

$nome     = trim($_GET['nome'] ?? '');
$cpf      = trim($_GET['cpf']  ?? '');
$filtroTodos = ($nome === '' && $cpf === '');

$registros = LogAcessoService::listar(
    $nome !== '' ? $nome : null,
    $cpf  !== '' ? $cpf  : null
);

$layoutTitulo = 'Log de Autenticação';
$layoutAtivo  = 'log';
require __DIR__ . '/_layout.php';
?>

<div class="page-heading">
    <div>
        <h1>Log de Autenticação</h1>
        <p>Histórico de acessos dos usuários comuns, do mais recente ao mais antigo.</p>
    </div>
</div>

<!-- Filtros + tabela: o filtro roda via AJAX (RNF08) — assets/js/filtro-ajax.js troca só este bloco -->
<div id="areaLog">
<div class="fh-card-inner" style="padding-bottom:1.2rem;">
    <form method="get" action="log_acesso.php" data-ajax-filtro="areaLog" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="fh-form-group" style="flex:1;min-width:200px;margin:0;">
            <label for="fNome">Nome do usuário</label>
            <input type="text" id="fNome" name="nome" class="fh-input"
                   placeholder="Substring do nome…"
                   value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="fh-form-group" style="min-width:180px;margin:0;">
            <label for="fCpf">CPF</label>
            <input type="text" id="fCpf" name="cpf" class="fh-input"
                   placeholder="Somente números"
                   maxlength="11"
                   value="<?= htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-fh">Filtrar</button>
        <?php if (!$filtroTodos): ?>
            <a href="log_acesso.php" data-ajax-filtro="areaLog" class="btn-fh-outline" style="text-decoration:none;display:inline-flex;align-items:center;">Todos</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabela de logs -->
<div class="fh-card-inner" style="padding-top:1rem;">
    <?php if (empty($registros)): ?>
        <div style="text-align:center;padding:2.5rem;color:#888;">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto 1rem;opacity:.35;"><path d="M12 3 4 6v5c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6l-8-3z"/></svg>
            <strong>Nenhum registro encontrado</strong>
            <?php if (!$filtroTodos): ?>
                <p style="margin:.5rem 0 0;font-size:.9rem;">Tente ampliar o filtro.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p style="margin:0 0 1rem;color:#666;font-size:.88rem;"><?= count($registros) ?> registro(s) encontrado(s)
            <?php if (!$filtroTodos): ?>
                — filtrando por
                <?php if ($nome !== ''): ?> nome "<strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>"<?php endif; ?>
                <?php if ($cpf  !== ''): ?> CPF <strong><?= htmlspecialchars($cpf,  ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
            <?php endif; ?>
        </p>
        <div style="overflow-x:auto;">
            <table class="fh-table">
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>2FA utilizado</th>
                        <th style="text-align:center;">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r):
                        $dt = date('d/m/Y H:i:s', strtotime($r['data_hora']));
                        $isSucesso = ($r['resultado'] === 'sucesso');
                    ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:.88rem;"><?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['nome_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><code style="background:var(--cream);padding:.1rem .4rem;border-radius:4px;font-size:.82rem;"><?= htmlspecialchars(
                            preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $r['cpf'] ?? ''),
                            ENT_QUOTES, 'UTF-8'
                        ) ?></code></td>
                        <td style="font-size:.9rem;"><?= htmlspecialchars($r['tipo_2fa_utilizado'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="text-align:center;">
                            <span class="fh-badge <?= $isSucesso ? 'fh-badge-ok' : 'fh-badge-falha' ?>">
                                <?= $isSucesso ? '✓ Sucesso' : '✗ Falha' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</div><!-- /areaLog -->

<?php
$layoutScripts = ['assets/js/filtro-ajax.js'];
require __DIR__ . '/_layout_fim.php';
?>
