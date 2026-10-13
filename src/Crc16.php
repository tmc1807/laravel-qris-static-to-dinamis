<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

/**
 * CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) as required by EMVCo tag 63.
 */
final class Crc16
{
    public static function compute(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
