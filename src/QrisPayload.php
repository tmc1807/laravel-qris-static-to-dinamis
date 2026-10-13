<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException;

/**
 * Root-level EMVCo TLV data objects of a QRIS payload.
 */
final class QrisPayload
{
    public const TAG_FORMAT_INDICATOR = '00';

    public const TAG_INITIATION_METHOD = '01';

    public const TAG_MERCHANT_CATEGORY = '52';

    public const TAG_CURRENCY = '53';

    public const TAG_AMOUNT = '54';

    public const TAG_TIP_INDICATOR = '55';

    public const TAG_FEE_FIXED = '56';

    public const TAG_FEE_PERCENTAGE = '57';

    public const TAG_COUNTRY = '58';

    public const TAG_MERCHANT_NAME = '59';

    public const TAG_MERCHANT_CITY = '60';

    public const TAG_POSTAL_CODE = '61';

    public const TAG_CRC = '63';

    public const STATIC = '11';

    public const DYNAMIC = '12';

    /**
     * @param  array<int|string, string>  $tags
     */
    private function __construct(private array $tags) {}

    public static function parse(string $payload, bool $verifyCrc = true): self
    {
        $payload = trim($payload);
        $length = mb_strlen($payload, 'UTF-8');
        $tags = [];
        $offset = 0;

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                throw new InvalidQrisException("Truncated data object at position {$offset}.");
            }

            $tag = mb_substr($payload, $offset, 2, 'UTF-8');
            $size = mb_substr($payload, $offset + 2, 2, 'UTF-8');

            if (! ctype_digit($tag) || ! ctype_digit($size)) {
                throw new InvalidQrisException("Malformed data object at position {$offset}.");
            }

            $size = (int) $size;

            if ($offset + 4 + $size > $length) {
                throw new InvalidQrisException("Data object {$tag} exceeds the payload length.");
            }

            if (array_key_exists($tag, $tags)) {
                throw new InvalidQrisException("Duplicate data object {$tag}.");
            }

            $tags[$tag] = mb_substr($payload, $offset + 4, $size, 'UTF-8');
            $offset += 4 + $size;
        }

        if (($tags[self::TAG_FORMAT_INDICATOR] ?? null) !== '01' || (string) array_key_first($tags) !== self::TAG_FORMAT_INDICATOR) {
            throw new InvalidQrisException('Payload must start with format indicator 000201.');
        }

        if (! isset($tags[self::TAG_CRC]) || (string) array_key_last($tags) !== self::TAG_CRC) {
            throw new InvalidQrisException('Payload must end with a CRC data object (tag 63).');
        }

        if ($verifyCrc) {
            $expected = Crc16::compute(substr($payload, 0, -4));

            if (strtoupper($tags[self::TAG_CRC]) !== $expected) {
                throw new InvalidQrisException("Invalid CRC: expected {$expected}, got {$tags[self::TAG_CRC]}.");
            }
        }

        return new self($tags);
    }

    public function get(string $tag): ?string
    {
        return $this->tags[$tag] ?? null;
    }

    public function has(string $tag): bool
    {
        return array_key_exists($tag, $this->tags);
    }

    public function with(string $tag, string $value): self
    {
        if (! preg_match('/^\d{2}$/', $tag)) {
            throw new \InvalidArgumentException("Invalid tag [{$tag}].");
        }

        if (mb_strlen($value, 'UTF-8') > 99) {
            throw new \InvalidArgumentException("Value for tag {$tag} exceeds 99 characters.");
        }

        $tags = $this->tags;
        $tags[$tag] = $value;

        return new self($tags);
    }

    public function without(string ...$tags): self
    {
        return new self(array_diff_key($this->tags, array_flip($tags)));
    }

    /**
     * Tags as keys in payload order. PHP casts tags without a leading zero
     * (e.g. "59") to integer keys, so cast with (string) when comparing.
     *
     * @return array<int|string, string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    public function isDynamic(): bool
    {
        return $this->get(self::TAG_INITIATION_METHOD) === self::DYNAMIC;
    }

    public function isStatic(): bool
    {
        return ! $this->isDynamic();
    }

    public function amount(): ?string
    {
        return $this->get(self::TAG_AMOUNT);
    }

    public function merchantName(): ?string
    {
        return $this->get(self::TAG_MERCHANT_NAME);
    }

    public function merchantCity(): ?string
    {
        return $this->get(self::TAG_MERCHANT_CITY);
    }

    public function postalCode(): ?string
    {
        return $this->get(self::TAG_POSTAL_CODE);
    }

    public function merchantCategoryCode(): ?string
    {
        return $this->get(self::TAG_MERCHANT_CATEGORY);
    }

    public function currency(): ?string
    {
        return $this->get(self::TAG_CURRENCY);
    }

    public function countryCode(): ?string
    {
        return $this->get(self::TAG_COUNTRY);
    }

    /**
     * Serialize with ascending tags (00 first) and a freshly computed CRC.
     */
    public function toString(): string
    {
        $tags = $this->tags;
        unset($tags[self::TAG_CRC]);
        ksort($tags, SORT_STRING);

        $payload = '';

        foreach ($tags as $tag => $value) {
            $payload .= $tag.str_pad((string) mb_strlen($value, 'UTF-8'), 2, '0', STR_PAD_LEFT).$value;
        }

        $payload .= self::TAG_CRC.'04';

        return $payload.Crc16::compute($payload);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
