<?php

require_once __DIR__ . '/../Repositories/ProdutoRepository.php';
require_once __DIR__ . '/../Repositories/CategoriaRepository.php';
require_once __DIR__ . '/../Repositories/InsumoRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/../Models/SetorPreparo.php';
require_once __DIR__ . '/Numero.php';
require_once __DIR__ . '/RegraNegocioException.php';

/** RF01/RF13/RF14 — cardápio, produtos, foto e ficha técnica (custo × margem). */
class ProdutoService
{
    private const FOTO_MAX_BYTES = 2097152; // 2 MB
    private const FOTO_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function pastaFotos(): string
    {
        return __DIR__ . '/../../public/uploads/produtos';
    }

    public static function listar(array $get): array
    {
        $ativo = null;
        if (isset($get['ativo']) && $get['ativo'] !== '') {
            $ativo = (int) ($get['ativo'] === '1' || $get['ativo'] === 1 || $get['ativo'] === true);
        }
        return array_map([self::class, 'formatar'], ProdutoRepository::listar([
            'id_categoria' => (int) ($get['id_categoria'] ?? 0),
            'ativo' => $ativo,
            'busca' => trim((string) ($get['busca'] ?? '')),
        ]));
    }

    /** Cardápio exibido ao cliente (RF01): só produtos ativos, por categoria, sem custo/margem. */
    public static function cardapio(): array
    {
        $descricoes = [];
        foreach (CategoriaRepository::listar() as $c) {
            $descricoes[(int) $c['id_categoria']] = $c['descricao'];
        }
        $grupos = [];
        foreach (ProdutoRepository::listar(['ativo' => 1]) as $p) {
            $idCat = (int) $p['id_categoria'];
            $grupos[$idCat] ??= ['id_categoria' => $idCat, 'categoria' => $p['categoria'], 'descricao' => $descricoes[$idCat] ?? null, 'produtos' => []];
            $item = self::formatar($p);
            unset($item['custo'], $item['margem'], $item['margem_percentual']);
            $grupos[$idCat]['produtos'][] = $item;
        }
        return array_values($grupos);
    }

    public static function buscar(int $id): array
    {
        $p = ProdutoRepository::buscar($id);
        if ($p === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        return self::formatar($p);
    }

    public static function salvar(array $entrada): array
    {
        $id = (int) ($entrada['id_produto'] ?? 0);
        $dados = self::validar($entrada);
        if ($id > 0) {
            if (ProdutoRepository::buscar($id) === null) {
                throw new RegraNegocioException('Produto não encontrado.');
            }
            ProdutoRepository::atualizar($id, $dados);
        } else {
            $id = ProdutoRepository::inserir($dados);
        }
        return ['id_produto' => $id];
    }

    public static function definirAtivo(int $id, bool $ativo): array
    {
        if (ProdutoRepository::buscar($id) === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        ProdutoRepository::definirAtivo($id, $ativo);
        return ['id_produto' => $id, 'ativo' => $ativo];
    }

    public static function excluir(int $id): array
    {
        $p = ProdutoRepository::buscar($id);
        if ($p === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        if (ProdutoRepository::temVendas($id)) {
            throw new RegraNegocioException('Este produto já foi vendido e não pode ser excluído (o histórico de pedidos depende dele). Inative-o para tirá-lo do cardápio.');
        }
        ProdutoRepository::excluir($id);
        self::apagarArquivoFoto($p['foto'] ?? null);
        return ['id_produto' => $id];
    }

    /* ── Ficha técnica (RF14) ───────────────────────────────────────── */

    public static function fichaTecnica(int $idProduto): array
    {
        $produto = ProdutoRepository::buscar($idProduto);
        if ($produto === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        $itens = [];
        $custo = 0.0;
        foreach (ProdutoRepository::ficha($idProduto) as $l) {
            $qtd = (float) $l['quantidade_utilizada'];
            $custoItem = $qtd * (float) $l['custo_unitario'];
            $custo += $custoItem;
            $itens[] = [
                'id_insumo' => (int) $l['id_insumo'], 'nome' => $l['nome'], 'unidade_medida' => $l['unidade_medida'],
                'quantidade' => $qtd, 'custo_unitario' => (float) $l['custo_unitario'], 'custo_item' => round($custoItem, 2),
            ];
        }
        $preco = (float) $produto['preco_venda'];
        return [
            'produto' => ['id_produto' => (int) $produto['id_produto'], 'nome' => $produto['nome'], 'preco_venda' => $preco],
            'itens' => $itens,
        ] + self::margem($preco, $custo);
    }

    /** @param array<int,array{id_insumo:mixed,quantidade:mixed}> $itens */
    public static function salvarFicha(int $idProduto, array $itens): array
    {
        if (ProdutoRepository::buscar($idProduto) === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        $limpos = [];
        foreach ($itens as $item) {
            $idInsumo = (int) ($item['id_insumo'] ?? 0);
            $qtd = Numero::paraFloat($item['quantidade'] ?? null);
            if ($idInsumo <= 0 || InsumoRepository::buscar($idInsumo) === null) {
                throw new RegraNegocioException('Insumo inválido na ficha técnica.');
            }
            if ($qtd === null || $qtd <= 0 || $qtd > 999999) {
                throw new RegraNegocioException('Informe uma quantidade maior que zero para cada insumo.');
            }
            if (isset($limpos[$idInsumo])) {
                throw new RegraNegocioException('O mesmo insumo foi informado mais de uma vez na ficha técnica.');
            }
            $limpos[$idInsumo] = ['id_insumo' => $idInsumo, 'quantidade' => round($qtd, 3)];
        }
        Transacao::executar(fn() => ProdutoRepository::substituirFicha($idProduto, array_values($limpos)));
        return self::fichaTecnica($idProduto);
    }

    /* ── Foto ──────────────────────────────────────────────────────── */

    /** @param array|null $arquivo entrada de $_FILES['foto'] */
    public static function salvarFoto(int $idProduto, ?array $arquivo): array
    {
        $produto = ProdutoRepository::buscar($idProduto);
        if ($produto === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($erro !== UPLOAD_ERR_OK) {
            throw new RegraNegocioException(
                in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'A imagem excede o tamanho máximo de 2 MB.' : 'Nenhuma imagem foi enviada.'
            );
        }
        if (($arquivo['size'] ?? 0) > self::FOTO_MAX_BYTES) {
            throw new RegraNegocioException('A imagem excede o tamanho máximo de 2 MB.');
        }
        if (!is_uploaded_file($arquivo['tmp_name']) && !defined('FOODHUB_TESTE')) {
            throw new RegraNegocioException('Upload inválido.');
        }
        // O tipo é decidido pelo CONTEÚDO, nunca pelo nome/extensão enviados.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::FOTO_TIPOS[$mime]) || @getimagesize($arquivo['tmp_name']) === false) {
            throw new RegraNegocioException('Formato inválido. Envie uma imagem JPG, PNG ou WebP.');
        }
        $pasta = self::pastaFotos();
        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta de uploads.');
        }
        $nome = sprintf('produto_%d_%s.%s', $idProduto, bin2hex(random_bytes(6)), self::FOTO_TIPOS[$mime]);
        $destino = $pasta . '/' . $nome;
        $moveu = defined('FOODHUB_TESTE') ? copy($arquivo['tmp_name'], $destino) : move_uploaded_file($arquivo['tmp_name'], $destino);
        if (!$moveu) {
            throw new RuntimeException('Falha ao gravar a imagem.');
        }
        ProdutoRepository::definirFoto($idProduto, 'uploads/produtos/' . $nome);
        self::apagarArquivoFoto($produto['foto'] ?? null);
        return ['foto' => 'uploads/produtos/' . $nome];
    }

    public static function removerFoto(int $idProduto): array
    {
        $produto = ProdutoRepository::buscar($idProduto);
        if ($produto === null) {
            throw new RegraNegocioException('Produto não encontrado.');
        }
        ProdutoRepository::definirFoto($idProduto, null);
        self::apagarArquivoFoto($produto['foto'] ?? null);
        return ['foto' => null];
    }

    /* ── Internos ──────────────────────────────────────────────────── */

    private static function apagarArquivoFoto(?string $rel): void
    {
        if (!$rel || !str_starts_with($rel, 'uploads/produtos/')) {
            return;
        }
        $arquivo = self::pastaFotos() . '/' . basename($rel);
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    private static function validar(array $e): array
    {
        $nome = trim((string) ($e['nome'] ?? ''));
        $descricao = trim((string) ($e['descricao'] ?? ''));
        $idCategoria = (int) ($e['id_categoria'] ?? 0);
        $preco = Numero::paraFloat($e['preco_venda'] ?? null);

        $len = mb_strlen($nome, 'UTF-8');
        if ($len < 2 || $len > 80) {
            throw new RegraNegocioException('O nome do produto deve ter de 2 a 80 caracteres.');
        }
        if (mb_strlen($descricao, 'UTF-8') > 300) {
            throw new RegraNegocioException('A descrição pode ter no máximo 300 caracteres.');
        }
        if ($idCategoria <= 0 || CategoriaRepository::buscar($idCategoria) === null) {
            throw new RegraNegocioException('Selecione uma categoria válida.');
        }
        if ($preco === null || $preco <= 0 || $preco > 99999.99) {
            throw new RegraNegocioException('Informe um preço de venda maior que zero (máximo R$ 99.999,99).');
        }
        // Setor de preparo: onde o item é feito. Ausente (clientes antigos da API) = cozinha.
        $setor = strtolower(trim((string) ($e['setor_preparo'] ?? SetorPreparo::COZINHA)));
        if (!SetorPreparo::valido($setor)) {
            throw new RegraNegocioException('Selecione o setor de preparo: Cozinha, Bar ou Sem preparo.');
        }
        return [
            'id_categoria' => $idCategoria, 'nome' => $nome, 'descricao' => $descricao === '' ? null : $descricao,
            'preco_venda' => number_format(round($preco, 2), 2, '.', ''),
            'ativo' => !empty($e['ativo']) && $e['ativo'] !== '0' ? 1 : 0,
            'setor_preparo' => $setor,
        ];
    }

    private static function formatar(array $p): array
    {
        $preco = (float) $p['preco_venda'];
        return [
            'id_produto' => (int) $p['id_produto'], 'id_categoria' => (int) $p['id_categoria'],
            'categoria' => $p['categoria'], 'nome' => $p['nome'], 'descricao' => $p['descricao'],
            'foto' => $p['foto'], 'preco_venda' => $preco, 'ativo' => (bool) $p['ativo'],
            'setor_preparo' => $p['setor_preparo'] ?? SetorPreparo::COZINHA,
            'setor_rotulo' => SetorPreparo::rotulo($p['setor_preparo'] ?? SetorPreparo::COZINHA),
        ] + self::margem($preco, (float) ($p['custo'] ?? 0));
    }

    /** Custo do prato, margem em R$ e em % sobre o preço de venda. */
    private static function margem(float $preco, float $custo): array
    {
        $custo = round($custo, 2);
        return [
            'custo' => $custo, 'margem' => round($preco - $custo, 2),
            'margem_percentual' => $preco > 0 ? round(($preco - $custo) * 100 / $preco, 1) : 0.0,
        ];
    }
}
