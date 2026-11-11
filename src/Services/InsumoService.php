<?php

require_once __DIR__ . '/../Repositories/InsumoRepository.php';
require_once __DIR__ . '/../Repositories/MovimentoEstoqueRepository.php';
require_once __DIR__ . '/../Repositories/Transacao.php';
require_once __DIR__ . '/../Models/UnidadeMedida.php';
require_once __DIR__ . '/../Models/TipoMovimento.php';
require_once __DIR__ . '/Numero.php';
require_once __DIR__ . '/ContaService.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/RegraNegocioException.php';

/** RF15/RF16 — cadastro de insumos, movimentação e alerta de estoque mínimo. */
class InsumoService
{
    public static function listar(array $get): array
    {
        $linhas = array_map([self::class, 'formatar'], InsumoRepository::listar(trim((string) ($get['busca'] ?? '')), ($get['baixo'] ?? '') === '1'));
        return [
            'insumos' => $linhas,
            'total_baixo' => count(array_filter($linhas, fn($i) => $i['abaixo_minimo'])),
            'unidades' => UnidadeMedida::ROTULOS,
        ];
    }

    public static function salvar(array $e, ?int $idUsuario): array
    {
        $id = (int) ($e['id_insumo'] ?? 0);
        $nome = trim((string) ($e['nome'] ?? ''));
        $un = strtolower(trim((string) ($e['unidade_medida'] ?? '')));
        $minimo = Numero::paraFloat($e['estoque_minimo'] ?? 0);
        $custo = Numero::paraFloat($e['custo_unitario'] ?? 0);

        $len = mb_strlen($nome, 'UTF-8');
        if ($len < 2 || $len > 80) {
            throw new RegraNegocioException('O nome do insumo deve ter de 2 a 80 caracteres.');
        }
        if (!in_array($un, UnidadeMedida::validas(), true)) {
            throw new RegraNegocioException('Selecione uma unidade de medida válida.');
        }
        if ($minimo === null || $minimo < 0 || $minimo > 9999999) {
            throw new RegraNegocioException('O estoque mínimo deve ser um número maior ou igual a zero.');
        }
        if ($custo === null || $custo < 0 || $custo > 999999) {
            throw new RegraNegocioException('O custo unitário deve ser um número maior ou igual a zero.');
        }
        if (InsumoRepository::nomeExiste($nome, $id)) {
            throw new RegraNegocioException('Já existe um insumo com este nome.');
        }
        $dados = ['nome' => $nome, 'unidade_medida' => $un, 'estoque_minimo' => round($minimo, 3), 'custo_unitario' => round($custo, 4)];

        if ($id > 0) {
            if (InsumoRepository::buscar($id) === null) {
                throw new RegraNegocioException('Insumo não encontrado.');
            }
            InsumoRepository::atualizar($id, $dados);
            return ['id_insumo' => $id];
        }

        $inicial = Numero::paraFloat($e['estoque_atual'] ?? 0);
        if ($inicial === null || $inicial < 0 || $inicial > 9999999) {
            throw new RegraNegocioException('O estoque inicial deve ser um número maior ou igual a zero.');
        }
        return Transacao::executar(function () use ($dados, $inicial, $idUsuario) {
            $id = InsumoRepository::inserir($dados + ['estoque_atual' => 0]);
            if ($inicial > 0) {
                self::aplicarMovimento($id, TipoMovimento::ENTRADA, $inicial, 'Estoque inicial', $idUsuario);
            }
            return ['id_insumo' => $id];
        });
    }

    public static function excluir(int $id): array
    {
        if (InsumoRepository::buscar($id) === null) {
            throw new RegraNegocioException('Insumo não encontrado.');
        }
        if (InsumoRepository::usadoEmFicha($id)) {
            throw new RegraNegocioException('Este insumo faz parte da ficha técnica de algum produto. Remova-o das fichas antes de excluir.');
        }
        Transacao::executar(fn() => InsumoRepository::excluir($id));
        return ['id_insumo' => $id];
    }

    /** Entrada, saída ou perda manual (RF15). */
    public static function movimentar(array $e, ?int $idUsuario): array
    {
        $id = (int) ($e['id_insumo'] ?? 0);
        $tipo = (string) ($e['tipo'] ?? '');
        $qtd = Numero::paraFloat($e['quantidade'] ?? null);
        $motivo = trim((string) ($e['motivo'] ?? ''));

        if (!in_array($tipo, TipoMovimento::validos(), true)) {
            throw new RegraNegocioException('Tipo de movimentação inválido.');
        }
        if ($qtd === null || $qtd <= 0 || $qtd > 9999999) {
            throw new RegraNegocioException('Informe uma quantidade maior que zero.');
        }
        if (mb_strlen($motivo, 'UTF-8') > 200) {
            throw new RegraNegocioException('O motivo pode ter no máximo 200 caracteres.');
        }
        if ($tipo === TipoMovimento::PERDA && $motivo === '') {
            throw new RegraNegocioException('Informe o motivo da perda (ex.: vencimento, queda, preparo errado).');
        }
        // Entrada de estoque pode gerar a conta a pagar da compra (opcional). Valida ANTES de mexer no estoque.
        $conta = null;
        if ($tipo === TipoMovimento::ENTRADA && is_array($e['gerar_conta'] ?? null) && !empty($e['gerar_conta']['ativo'])) {
            $insumo = InsumoRepository::buscar($id);
            $nome = $insumo['nome'] ?? 'insumo';
            $g = $e['gerar_conta'];
            $g['descricao'] = $g['descricao'] ?? ('Compra: ' . $nome);
            $conta = ContaService::validarCompra($g);
        }
        return Transacao::executar(function () use ($id, $tipo, $qtd, $motivo, $idUsuario, $conta) {
            $r = self::formatar(self::aplicarMovimento($id, $tipo, round($qtd, 3), $motivo === '' ? null : $motivo, $idUsuario));
            if ($conta !== null) {
                $r['id_conta'] = ContaService::inserirValidada($conta);   // na mesma transação da entrada
            }
            return $r;
        });
    }

    public static function movimentos(array $get): array
    {
        $limite = max(1, min((int) ($get['limite'] ?? 50), 200));
        return array_map(fn($m) => [
            'id_movimento' => (int) $m['id_movimento'], 'id_insumo' => (int) $m['id_insumo'], 'insumo' => $m['insumo'],
            'unidade_medida' => $m['unidade_medida'], 'tipo' => $m['tipo'], 'quantidade' => (float) $m['quantidade'],
            'data_hora' => $m['data_hora'], 'motivo' => $m['motivo'], 'usuario' => $m['usuario'] ?? 'Sistema',
        ], MovimentoEstoqueRepository::listar((int) ($get['id_insumo'] ?? 0), $limite));
    }

    /**
     * Grava o movimento e atualiza o saldo. Roda em transação (trava a linha do insumo
     * para que duas baixas simultâneas não gerem saldo negativo). Retorna o insumo atualizado.
     */
    public static function aplicarMovimento(int $idInsumo, string $tipo, float $qtd, ?string $motivo, ?int $idUsuario): array
    {
        $insumo = InsumoRepository::buscar($idInsumo, true);
        if ($insumo === null) {
            throw new RegraNegocioException('Insumo não encontrado.');
        }
        $delta = TipoMovimento::sinal($tipo) * $qtd;
        $saldo = round((float) $insumo['estoque_atual'] + $delta, 3);
        if ($saldo < 0) {
            $fmt = fn(float $v) => rtrim(rtrim(number_format($v, 3, ',', ''), '0'), ',');
            throw new RegraNegocioException(sprintf(
                'Estoque insuficiente de "%s": disponível %s %s, solicitado %s %s.',
                $insumo['nome'], $fmt((float) $insumo['estoque_atual']), $insumo['unidade_medida'], $fmt($qtd), $insumo['unidade_medida']
            ));
        }
        InsumoRepository::somarEstoque($idInsumo, $delta);
        MovimentoEstoqueRepository::inserir($idInsumo, $idUsuario, $tipo, $qtd, $motivo, PeriodoService::agora()->format('Y-m-d H:i:s'));
        return InsumoRepository::buscar($idInsumo) ?? $insumo;
    }

    public static function formatar(array $i): array
    {
        $atual = (float) $i['estoque_atual'];
        $minimo = (float) $i['estoque_minimo'];
        return [
            'id_insumo' => (int) $i['id_insumo'], 'nome' => $i['nome'], 'unidade_medida' => $i['unidade_medida'],
            'estoque_atual' => $atual, 'estoque_minimo' => $minimo, 'custo_unitario' => (float) $i['custo_unitario'],
            'abaixo_minimo' => $atual < $minimo, 'valor_em_estoque' => round($atual * (float) $i['custo_unitario'], 2),
        ];
    }
}
