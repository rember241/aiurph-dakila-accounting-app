<?php

namespace Modules\Accounting\Services\Cheques;

final class AmountToWordsService
{
    private const ONES = [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety',
    ];

    public function pesoAmount(float $amount): string
    {
        $amount = round($amount, 2);
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        if ($centavos === 100) {
            $pesos++;
            $centavos = 0;
        }

        $words = $this->integerToWords($pesos).' '.($pesos === 1 ? 'Peso' : 'Pesos');

        if ($centavos > 0) {
            $words .= ' and '.$this->integerToWords($centavos).' '.($centavos === 1 ? 'Centavo' : 'Centavos');
        }

        return $words.' Only';
    }

    private function integerToWords(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $remainder = $number % 10;

            return self::TENS[$ten].($remainder > 0 ? '-'.self::ONES[$remainder] : '');
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;

            return self::ONES[$hundreds].' Hundred'.($remainder > 0 ? ' '.$this->integerToWords($remainder) : '');
        }

        foreach ([
            1_000_000_000 => 'Billion',
            1_000_000 => 'Million',
            1_000 => 'Thousand',
        ] as $value => $label) {
            if ($number >= $value) {
                $major = intdiv($number, $value);
                $remainder = $number % $value;

                return $this->integerToWords($major).' '.$label.($remainder > 0 ? ' '.$this->integerToWords($remainder) : '');
            }
        }

        return (string) $number;
    }
}
