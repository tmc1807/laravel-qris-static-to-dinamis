<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use InvalidArgumentException;

/**
 * Formats amounts for EMVCo tags 54 and 56: digits with an optional "." and
 * at most two decimals, no longer than 13 characters.
 */
final class Amount
{
    public static function format(int|float|string $amount): string
    {
        if (is_string($amount)) {
            $amount = trim($amount);
        }

        if (! is_numeric($amount) || ! preg_match('/^\d+(\.\d+)?$/', (string) $amount)) {
            throw new InvalidArgumentException("Invalid amount [{$amount}].");
        }

        $value = rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');

        if ((float) $value <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (strlen($value) > 13) {
            throw new InvalidArgumentException('Amount must not exceed 13 characters.');
        }

        return $value;
    }
}
