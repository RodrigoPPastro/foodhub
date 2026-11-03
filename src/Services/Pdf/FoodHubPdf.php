<?php

require_once __DIR__ . '/../../../lib/fpdf/fpdf.php';

/**
 * Documento FPDF com a identidade FOOD HUB (vermelho/dourado/preto):
 * faixa de cabeçalho em todas as páginas e rodapé com paginação.
 * Só cuida de layout genérico; o conteúdo de cada relatório fica no PdfService.
 */
class FoodHubPdf extends FPDF
{
    public const VERMELHO = [179, 22, 27];
    public const DOURADO  = [201, 154, 46];
    public const PRETO    = [26, 26, 26];

    public function __construct(private string $titulo, string $orientacao = 'P')
    {
        parent::__construct($orientacao, 'mm', 'A4');
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 18);
        $this->SetTitle(self::txt($titulo));
        $this->SetAuthor('FOOD HUB');
        $this->AliasNbPages();
    }

    /** FPDF trabalha em ISO-8859-1/cp1252; o projeto é UTF-8. */
    public static function txt(string $texto): string
    {
        $convertido = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $texto);
        return $convertido === false ? '' : $convertido;
    }

    public function Header(): void
    {
        $largura = $this->GetPageWidth();

        $this->SetFillColor(...self::VERMELHO);
        $this->Rect(0, 0, $largura, 22, 'F');
        $this->SetFillColor(...self::DOURADO);
        $this->Rect(0, 22, $largura, 1.5, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 18);
        $this->SetXY(10, 6);
        $this->Cell(80, 10, 'FOOD HUB', 0, 0, 'L');

        $this->SetFont('Helvetica', '', 11);
        $this->SetXY($largura - 130, 7);
        $this->Cell(120, 8, self::txt($this->titulo), 0, 0, 'R');

        $this->SetTextColor(...self::PRETO);
        $this->SetY(30);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 6, self::txt('Gerado em ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->SetX(10);
        $this->Cell(0, 6, self::txt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /** Corta o texto com "…" para caber na célula (evita invadir a coluna vizinha). */
    public function ajustar(string $texto, float $largura): string
    {
        $texto = self::txt($texto);
        $max = $largura - 2;
        if ($this->GetStringWidth($texto) <= $max) {
            return $texto;
        }
        while ($texto !== '' && $this->GetStringWidth($texto . '...') > $max) {
            $texto = substr($texto, 0, -1);
        }
        return $texto . '...';
    }

    /**
     * Tabela com cabeçalho repetido a cada página e linhas zebradas.
     * @param string[] $cabecalhos
     * @param float[]  $larguras
     * @param string[] $alinhamentos 'L'|'R'|'C' por coluna
     * @param array<int,string[]> $linhas
     * @param string[]|null $rodape linha de totais (opcional, em negrito)
     */
    public function tabela(array $cabecalhos, array $larguras, array $alinhamentos, array $linhas, ?array $rodape = null): void
    {
        $desenharCabecalho = function () use ($cabecalhos, $larguras, $alinhamentos) {
            $this->SetFillColor(...self::PRETO);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 9);
            foreach ($cabecalhos as $i => $c) {
                $this->Cell($larguras[$i], 7, self::txt($c), 0, 0, $alinhamentos[$i], true);
            }
            $this->Ln();
            $this->SetTextColor(...self::PRETO);
        };

        $desenharCabecalho();
        $this->SetFont('Helvetica', '', 9);

        foreach ($linhas as $n => $linha) {
            if ($this->GetY() + 7 > $this->PageBreakTrigger) {
                $this->AddPage();
                $desenharCabecalho();
                $this->SetFont('Helvetica', '', 9);
            }
            $this->SetFillColor($n % 2 === 0 ? 250 : 242, $n % 2 === 0 ? 245 : 235, $n % 2 === 0 ? 232 : 214);
            foreach ($linha as $i => $valor) {
                $this->Cell($larguras[$i], 7, $this->ajustar($valor, $larguras[$i]), 0, 0, $alinhamentos[$i], true);
            }
            $this->Ln();
        }

        if ($rodape !== null) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetFillColor(...self::DOURADO);
            foreach ($rodape as $i => $valor) {
                $this->Cell($larguras[$i], 7, $this->ajustar($valor, $larguras[$i]), 0, 0, $alinhamentos[$i], true);
            }
            $this->Ln();
        }
        $this->Ln(4);
    }

    public function subtitulo(string $texto): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::VERMELHO);
        $this->Cell(0, 8, self::txt($texto), 0, 1);
        $this->SetTextColor(...self::PRETO);
    }
}
