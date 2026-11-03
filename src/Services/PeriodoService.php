<?php

require_once __DIR__ . '/RegraNegocioException.php';

/**
 * Valida datas/períodos vindos da tela e os converte em intervalo
 * semiaberto [de, ate) — `data_hora >= de AND data_hora < ate` — que
 * permite ao MySQL usar o índice (ao contrário de DATE(coluna) = ...).
 */
class PeriodoService
{
    private const FUSO = 'America/Sao_Paulo';
    private const MAX_DIAS = 366;

    public static function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::FUSO));
    }

    public static function hoje(): string
    {
        return self::agora()->format('Y-m-d');
    }

    /** Um único dia (vazio = hoje). */
    public static function dia(?string $data): array
    {
        $d = self::converter(($data !== null && $data !== '') ? $data : self::hoje());
        return self::montar($d, $d);
    }

    /** Intervalo de dias (fim vazio = hoje; início vazio = últimos $diasPadrao dias). */
    public static function intervalo(?string $inicio, ?string $fim, int $diasPadrao = 30): array
    {
        $f = self::converter(($fim !== null && $fim !== '') ? $fim : self::hoje());
        $i = ($inicio !== null && $inicio !== '')
            ? self::converter($inicio)
            : $f->modify('-' . ($diasPadrao - 1) . ' days');

        if ($i > $f) {
            throw new RegraNegocioException('A data inicial não pode ser posterior à data final.');
        }
        if ($i->diff($f)->days >= self::MAX_DIAS) {
            throw new RegraNegocioException('O período máximo para consulta é de 366 dias.');
        }

        return self::montar($i, $f);
    }

    private static function converter(string $valor): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone(self::FUSO));

        // Compara de volta: 2026-02-30 "rola" para março e seria aceito sem isto.
        if ($d === false || $d->format('Y-m-d') !== $valor) {
            throw new RegraNegocioException('Data inválida. Use o formato AAAA-MM-DD.');
        }
        return $d;
    }

    private static function montar(DateTimeImmutable $i, DateTimeImmutable $f): array
    {
        return [
            'inicio' => $i->format('Y-m-d'),
            'fim'    => $f->format('Y-m-d'),
            'de'     => $i->format('Y-m-d') . ' 00:00:00',
            'ate'    => $f->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
    }
}
