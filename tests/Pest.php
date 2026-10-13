<?php

use Tmc1807\LaravelQrisStaticToDinamis\Crc16;
use Tmc1807\LaravelQrisStaticToDinamis\Tests\TestCase;
use Zxing\QrReader;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a payload from TLV pairs in the given order and append a valid CRC.
 *
 * @param  array<string, string>  $tags
 */
function qris(array $tags): string
{
    $payload = '';

    foreach ($tags as $tag => $value) {
        $payload .= $tag.str_pad((string) mb_strlen($value, 'UTF-8'), 2, '0', STR_PAD_LEFT).$value;
    }

    $payload .= '6304';

    return $payload.Crc16::compute($payload);
}

function staticQris(): string
{
    return qris([
        '00' => '01',
        '01' => '11',
        '26' => '0016ID.CO.QRIS.WWW0118936009153000000001021012345678901230303UMI',
        '51' => '0014ID.CO.QRIS.WWW0215ID20231234567890303UMI',
        '52' => '5812',
        '53' => '360',
        '58' => 'ID',
        '59' => 'WARUNG MAKAN TMC',
        '60' => 'JAKARTA',
        '61' => '12345',
    ]);
}

function decodeQr(string $png): string
{
    $file = tempnam(sys_get_temp_dir(), 'qris').'.png';
    file_put_contents($file, $png);

    try {
        return (string) (new QrReader($file))->text();
    } finally {
        @unlink($file);
    }
}
