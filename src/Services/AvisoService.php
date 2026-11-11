<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/PeriodoService.php';
require_once __DIR__ . '/ContaService.php';

/**
 * Janela de avisos que o Master vê ao entrar no sistema:
 *   estoque zerado · estoque abaixo do mínimo · contas a pagar vencidas e vencendo hoje/amanhã ·
 *   contas a receber (repasses) vencidas.
 * Também monta a frase que o navegador lê em voz alta.
 */
class AvisoService
{
    public static function pendencias(): array
    {
        $pdo = Database::getConnection();
        $hoje = PeriodoService::hoje();
        $amanha = (new DateTimeImmutable($hoje))->modify('+1 day')->format('Y-m-d');

        // Repasses de pedidos externos que ainda não viraram conta (autocorreção já existente)
        try { ContaService::gerarRepassesPendentes(); } catch (Throwable $e) { /* não impede os avisos */ }

        $insumos = $pdo->query(
            'SELECT id_insumo, nome, unidade_medida, estoque_atual, estoque_minimo FROM insumo
             WHERE estoque_atual <= 0 OR estoque_atual < estoque_minimo
             ORDER BY estoque_atual > 0, (estoque_minimo - estoque_atual) DESC, nome'
        )->fetchAll();
        $zerados = [];
        $baixos = [];
        foreach ($insumos as $i) {
            $item = ['nome' => $i['nome'], 'unidade' => $i['unidade_medida'],
                     'atual' => (float) $i['estoque_atual'], 'minimo' => (float) $i['estoque_minimo']];
            if ((float) $i['estoque_atual'] <= 0) {
                $zerados[] = $item;
            } else {
                $baixos[] = $item;
            }
        }

        $contas = function (string $tipo, string $condicao, array $params) use ($pdo, $hoje) {
            $stmt = $pdo->prepare(
                "SELECT id_conta, descricao, pessoa, valor, data_vencimento, DATEDIFF(:hoje, data_vencimento) AS dias
                 FROM conta_financeira WHERE tipo = :tipo AND status = 'aberta' AND $condicao
                 ORDER BY data_vencimento, id_conta"
            );
            $stmt->execute(['tipo' => $tipo, 'hoje' => $hoje] + $params);
            return array_map(fn($c) => [
                'id_conta' => (int) $c['id_conta'], 'descricao' => $c['descricao'], 'pessoa' => $c['pessoa'],
                'valor' => (float) $c['valor'], 'vencimento' => $c['data_vencimento'], 'dias_atraso' => max(0, (int) $c['dias']),
            ], $stmt->fetchAll());
        };
        $pagarVencidas  = $contas('pagar', 'data_vencimento < :ref', ['ref' => $hoje]);
        $pagarHoje      = $contas('pagar', 'data_vencimento = :ref', ['ref' => $hoje]);
        $pagarAmanha    = $contas('pagar', 'data_vencimento = :ref', ['ref' => $amanha]);
        $receberVencidas = $contas('receber', 'data_vencimento < :ref', ['ref' => $hoje]);

        $soma = fn(array $l) => round(array_sum(array_column($l, 'valor')), 2);
        $grupos = array_values(array_filter([
            ['chave' => 'estoque_zerado', 'titulo' => 'Estoque zerado', 'nivel' => 'critico', 'link' => 'estoque.php', 'itens' => $zerados],
            ['chave' => 'estoque_baixo', 'titulo' => 'Estoque abaixo do mínimo', 'nivel' => 'atencao', 'link' => 'estoque.php', 'itens' => $baixos],
            ['chave' => 'pagar_vencidas', 'titulo' => 'Contas a pagar vencidas', 'nivel' => 'critico', 'link' => 'contas.php',
             'total' => $soma($pagarVencidas), 'itens' => $pagarVencidas],
            ['chave' => 'pagar_hoje', 'titulo' => 'Contas a pagar que vencem hoje', 'nivel' => 'atencao', 'link' => 'contas.php',
             'total' => $soma($pagarHoje), 'itens' => $pagarHoje],
            ['chave' => 'pagar_amanha', 'titulo' => 'Contas a pagar que vencem amanhã', 'nivel' => 'info', 'link' => 'contas.php',
             'total' => $soma($pagarAmanha), 'itens' => $pagarAmanha],
            ['chave' => 'receber_vencidas', 'titulo' => 'Repasses / contas a receber vencidos', 'nivel' => 'atencao', 'link' => 'contas.php',
             'total' => $soma($receberVencidas), 'itens' => $receberVencidas],
        ], fn($g) => $g['itens'] !== []));

        return [
            'gerado_em' => PeriodoService::agora()->format('d/m/Y H:i'),
            'tudo_em_dia' => $grupos === [],
            'grupos' => $grupos,
            'fala' => self::frase($zerados, $baixos, $pagarVencidas, $pagarHoje, $pagarAmanha, $receberVencidas),
        ];
    }

    /** Resumo curto e natural para a leitura em voz alta. */
    private static function frase(array $zerados, array $baixos, array $pv, array $ph, array $pa, array $rv): string
    {
        $qtd = function (int $n, string $um, string $varios) { return $n . ' ' . ($n === 1 ? $um : $varios); };
        $partes = [];
        if ($zerados) { $partes[] = $qtd(count($zerados), 'item de estoque zerado', 'itens de estoque zerados'); }
        if ($baixos)  { $partes[] = $qtd(count($baixos), 'item abaixo do mínimo', 'itens abaixo do mínimo'); }
        if ($pv)      { $partes[] = $qtd(count($pv), 'conta a pagar vencida', 'contas a pagar vencidas'); }
        if ($ph)      { $partes[] = $qtd(count($ph), 'conta que vence hoje', 'contas que vencem hoje'); }
        if ($pa)      { $partes[] = $qtd(count($pa), 'conta que vence amanhã', 'contas que vencem amanhã'); }
        if ($rv)      { $partes[] = $qtd(count($rv), 'repasse a receber vencido', 'repasses a receber vencidos'); }

        if ($partes === []) {
            return 'Tudo em dia! Nenhuma pendência no momento.';
        }
        $ultima = array_pop($partes);
        return 'Atenção: ' . ($partes ? implode(', ', $partes) . ' e ' : '') . $ultima . '.';
    }
}
