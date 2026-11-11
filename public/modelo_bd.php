<?php
/** RF08 — exibe o Diagrama Entidade-Relacionamento do banco (disponível para Master e Cliente). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requireLogin();
$layoutTitulo = 'Modelo do Banco de Dados';
$layoutAtivo  = 'modelo';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Modelo do Banco de Dados</h1><p>Diagrama Entidade-Relacionamento (DER) do FOOD HUB.</p></div>
</div>
<div class="fh-card-inner">
    <div class="der-wrap"><img src="assets/img/der.png" alt="Diagrama Entidade-Relacionamento do banco de dados do FOOD HUB"></div>
    <p class="fh-hint">Role para os lados para ver o diagrama inteiro. O script completo está em <code>database/foodhub_completo.sql</code>.</p>
</div>
<?php require __DIR__ . '/_layout_fim.php';
