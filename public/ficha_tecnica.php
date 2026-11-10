<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/EstoqueController.php';
AuthGuard::requireMaster();

$c = new EstoqueController();
$produtos = $c->listarProdutos();
$insumos = $c->listarAtivos();
$idProduto = filter_input(INPUT_GET, 'produto', FILTER_VALIDATE_INT) ?: 0;
$produtoSelecionado = null;
$ficha = [];
$custo = 0.0;
if ($idProduto > 0) {
    foreach ($produtos as $p) {
        if ((int)$p['id_produto'] === $idProduto) { $produtoSelecionado = $p; break; }
    }
    if ($produtoSelecionado) {
        $ficha = $c->listarFicha($idProduto);
        $custo = $c->custoFicha($idProduto);
    }
}
function hFicha($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$margem = ($produtoSelecionado && (float)$produtoSelecionado['preco_venda'] > 0)
    ? (((float)$produtoSelecionado['preco_venda'] - $custo) / (float)$produtoSelecionado['preco_venda']) * 100
    : null;
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>FOOD HUB - Ficha Técnica</title>
<style>body{font-family:Arial;margin:24px;background:#f7f7f7;color:#222}main{max-width:950px;margin:auto}section{background:white;padding:18px;margin:18px 0;border-radius:8px}form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}label{display:flex;flex-direction:column;gap:5px}input,select{padding:9px}button{padding:10px 16px;background:#b51f2a;color:white;border:0;border-radius:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:9px;text-align:left}</style></head><body><main>
<h1>Ficha técnica do produto</h1><p><a href="estoque.php">Voltar ao estoque</a></p>
<?php if(isset($_GET['msg'])): ?><p><?= hFicha($_GET['msg']) ?></p><?php endif; ?>
<section><h2>Selecionar produto</h2>
<form method="get"><label>Produto<select name="produto" required><option value="">Selecione</option>
<?php foreach($produtos as $p): ?><option value="<?= (int)$p['id_produto'] ?>" <?= $idProduto===(int)$p['id_produto']?'selected':'' ?>><?= hFicha($p['nome']) ?></option><?php endforeach; ?>
</select></label><button type="submit">Abrir ficha</button></form></section>
<?php if($produtoSelecionado): ?>
<section><h2><?= hFicha($produtoSelecionado['nome']) ?></h2>
<form method="post" action="ficha_tecnica_process.php">
<input type="hidden" name="acao" value="salvar">
<input type="hidden" name="id_produto" value="<?= $idProduto ?>">
<label>Insumo<select name="id_insumo" required><option value="">Selecione</option>
<?php foreach($insumos as $i): ?><option value="<?= (int)$i['id_insumo'] ?>"><?= hFicha($i['nome']) ?> (<?= hFicha($i['unidade_medida']) ?>)</option><?php endforeach; ?>
</select></label>
<label>Quantidade utilizada<input type="number" name="quantidade_utilizada" min="0.001" step="0.001" required></label>
<button type="submit">Adicionar / atualizar ingrediente</button></form>
<table style="margin-top:18px"><thead><tr><th>Insumo</th><th>Quantidade</th><th>Custo unitário</th><th>Custo do ingrediente</th><th>Ação</th></tr></thead><tbody>
<?php foreach($ficha as $item): ?><tr>
<td><?= hFicha($item['nome']) ?></td><td><?= number_format((float)$item['quantidade_utilizada'],3,',','.') ?> <?= hFicha($item['unidade_medida']) ?></td>
<td>R$ <?= number_format((float)$item['custo_unitario'],4,',','.') ?></td><td>R$ <?= number_format((float)$item['custo_item'],2,',','.') ?></td>
<td><form method="post" action="ficha_tecnica_process.php" onsubmit="return confirm('Remover este ingrediente?')">
<input type="hidden" name="acao" value="remover"><input type="hidden" name="id_produto" value="<?= $idProduto ?>"><input type="hidden" name="id_insumo" value="<?= (int)$item['id_insumo'] ?>"><button type="submit">Remover</button></form></td>
</tr><?php endforeach; ?>
</tbody></table>
<p><strong>Custo total do produto:</strong> R$ <?= number_format($custo,2,',','.') ?></p>
<p><strong>Preço de venda:</strong> R$ <?= number_format((float)$produtoSelecionado['preco_venda'],2,',','.') ?></p>
<?php if($margem !== null): ?><p><strong>Margem bruta sobre o preço de venda:</strong> <?= number_format($margem,2,',','.') ?>%</p><?php endif; ?>
<p><small>O cálculo considera apenas o custo dos insumos cadastrados na ficha técnica; não inclui mão de obra, embalagem, energia ou impostos.</small></p>
</section>
<?php endif; ?>
</main></body></html>
