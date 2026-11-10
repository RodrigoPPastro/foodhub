<?php
// Inclusão opcional no Dashboard (RF16).
// A página dashboard.php deve exigir AuthGuard::requireMaster() antes desta inclusão.
require_once __DIR__ . '/../../src/Controllers/EstoqueController.php';
$alertasEstoqueDashboard = (new EstoqueController())->listarEstoqueBaixo();
?>
<section class="foodhub-alerta-estoque">
    <h2>Estoque baixo</h2>
    <?php if (empty($alertasEstoqueDashboard)): ?>
        <p>Nenhum insumo abaixo do estoque mínimo.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($alertasEstoqueDashboard as $alerta): ?>
                <li>
                    <strong><?= htmlspecialchars($alerta['nome'], ENT_QUOTES, 'UTF-8') ?></strong>:
                    <?= number_format((float)$alerta['estoque_atual'], 3, ',', '.') ?>
                    <?= htmlspecialchars($alerta['unidade_medida'], ENT_QUOTES, 'UTF-8') ?>
                    (mínimo: <?= number_format((float)$alerta['estoque_minimo'], 3, ',', '.') ?>).
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="estoque.php">Abrir controle de estoque</a>
    <?php endif; ?>
</section>
