<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use InvalidArgumentException;

/**
 * Tip or convenience fee indicator (EMVCo tags 55, 56 and 57).
 */
final class Fee
{
    private function __construct(
        public readonly string $indicator,
        public readonly ?string $tag = null,
        public readonly ?string $value = null,
    ) {}

    /**
     * Let the customer enter a tip in their payment app.
     */
    public static function prompt(): self
    {
        return new self('01');
    }

    /**
     * Fixed convenience fee in the transaction currency, e.g. 1000 for Rp1.000.
     */
    public static function fixed(int|float|string $amount): self
    {
        return new self('02', QrisPayload::TAG_FEE_FIXED, Amount::format($amount));
    }

    /**
     * Percentage convenience fee between 0.01 and 99.99, e.g. 0.7 for 0,7%.
     */
    public static function percent(int|float|string $percentage): self
    {
        if (! is_numeric($percentage) || (float) $percentage <= 0 || (float) $percentage >= 100) {
            throw new InvalidArgumentException('Fee percentage must be greater than 0 and less than 100.');
        }

        $value = rtrim(rtrim(number_format((float) $percentage, 2, '.', ''), '0'), '.');

        if ($value === '0') {
            throw new InvalidArgumentException('Fee percentage must be at least 0.01.');
        }

        return new self('03', QrisPayload::TAG_FEE_PERCENTAGE, $value);
    }

    public function applyTo(QrisPayload $payload): QrisPayload
    {
        $payload = $payload->with(QrisPayload::TAG_TIP_INDICATOR, $this->indicator);

        return $this->tag === null ? $payload : $payload->with($this->tag, $this->value);
    }
}
