<?php

require_once __DIR__ . '/Pdf/FoodHubPdf.php';
require_once __DIR__ . '/Dinheiro.php';

/**
 * Renderização de PDFs (RF23 e RF26). É a ÚNICA classe que conhece a FPDF:
 * para trocar por TCPDF/mPDF basta reescrever este arquivo e o FoodHubPdf.
 * Recebe dados prontos (arrays) e devolve os bytes do PDF — não consulta banco.
 */
class PdfService
{
    /** @param array $f resultado de FechamentoCaixa::toArray() */
    public static function fechamentoCaixa(array $f): string
    {
        $pdf = new FoodHubPdf('Fechamento de Caixa');
        $pdf->AddPage();

        $data = DateTimeImmutable::createFromFormat('Y-m-d', $f['data_referencia'])->format('d/m/Y');

        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(0, 9, FoodHubPdf::txt('Fechamento de caixa - ' . $data), 0, 1);

        // Situação: prévia não pode ser confundida com documento oficial.
        $pdf->SetFont('Helvetica', 'B', 10);
        if ($f['fechado']) {
            $pdf->SetTextColor(30, 110, 50);
            $situacao = 'CAIXA FECHADO' . ($f['responsavel'] ? ' por ' . $f['responsavel'] : '');
        } else {
            $pdf->SetTextColor(...FoodHubPdf::VERMELHO);
            $situacao = 'PRÉVIA - caixa ainda não fechado';
        }
        $pdf->Cell(0, 6, FoodHubPdf::txt($situacao), 0, 1);
        $pdf->SetTextColor(...FoodHubPdf::PRETO);
        $pdf->Ln(3);

        // Resumo
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(...FoodHubPdf::DOURADO);
        $pdf->Cell(63, 9, FoodHubPdf::txt('Total geral: ' . self::moeda($f['total_geral'])), 0, 0, 'C', true);
        $pdf->Cell(2, 9, '', 0, 0);
        $pdf->Cell(63, 9, FoodHubPdf::txt('Pedidos: ' . $f['quantidade_pedidos']), 0, 0, 'C', true);
        $pdf->Cell(2, 9, '', 0, 0);
        $pdf->Cell(60, 9, FoodHubPdf::txt('Ticket médio: ' . self::moeda($f['ticket_medio'])), 0, 1, 'C', true);
        $pdf->Ln(6);

        $pdf->subtitulo('Totais por forma de pagamento');
        $pdf->tabela(
            ['Forma de pagamento', 'Total', '% do total'],
            [100, 50, 40],
            ['L', 'R', 'R'],
            array_map(fn($x) => [$x['rotulo'], self::moeda($x['total']), self::percentual($x['percentual'])], $f['por_forma']),
            ['Total geral', self::moeda($f['total_geral']), '100,0%']
        );

        $pdf->subtitulo('Totais por canal de venda');
        $pdf->tabela(
            ['Canal', 'Pedidos', 'Total', '% do total'],
            [70, 30, 50, 40],
            ['L', 'R', 'R', 'R'],
            array_map(fn($x) => [$x['rotulo'], (string) $x['pedidos'], self::moeda($x['total']), self::percentual($x['percentual'])], $f['por_canal']),
            ['Total geral', (string) $f['quantidade_pedidos'], self::moeda($f['total_geral']), '100,0%']
        );

        if (!empty($f['observacoes'])) {
            $pdf->subtitulo('Observações');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(0, 5, FoodHubPdf::txt($f['observacoes']));
        }

        return $pdf->Output('S');
    }

    /** @param array<int,array<string,mixed>> $usuarios linhas de UsuarioExportacaoRepository */
    public static function listaUsuarios(array $usuarios, string $geradoPor): string
    {
        $pdf = new FoodHubPdf('Lista de Usuários', 'L');
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(0, 9, FoodHubPdf::txt('Usuários cadastrados (' . count($usuarios) . ')'), 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, FoodHubPdf::txt('Emitido por: ' . $geradoPor), 0, 1);
        $pdf->Ln(3);

        $linhas = array_map(fn($u) => [
            $u['nome_completo'],
            $u['login'],
            $u['email'],
            $u['telefone_celular'],
            self::mascararCpf((string) $u['cpf']),
            ['master' => 'Master', 'funcionario' => 'Funcionário'][$u['perfil']] ?? 'Cliente',
            date('d/m/Y', strtotime((string) $u['data_cadastro'])),
        ], $usuarios);

        $pdf->tabela(
            ['Nome', 'Login', 'E-mail', 'Celular', 'CPF', 'Perfil', 'Cadastro'],
            [62, 20, 62, 36, 32, 20, 25],
            ['L', 'L', 'L', 'L', 'L', 'L', 'L'],
            $linhas
        );

        return $pdf->Output('S');
    }

    /** LGPD: mostra só os dígitos centrais (***.456.789-**). */
    private static function mascararCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        return strlen($d) === 11 ? '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**' : '***';
    }

    private static function moeda(float $valor): string
    {
        return Dinheiro::formatar(Dinheiro::centavos($valor));
    }

    private static function percentual(float $valor): string
    {
        return number_format($valor, 1, ',', '.') . '%';
    }
}
