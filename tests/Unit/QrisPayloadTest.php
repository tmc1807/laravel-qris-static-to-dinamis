<?php

use Tmc1807\LaravelQrisStaticToDinamis\Crc16;
use Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException;
use Tmc1807\LaravelQrisStaticToDinamis\QrisPayload;

it('parses merchant information from a static payload', function (): void {
    $qris = QrisPayload::parse(staticQris());

    expect($qris->isStatic())->toBeTrue()
        ->and($qris->merchantName())->toBe('WARUNG MAKAN TMC')
        ->and($qris->merchantCity())->toBe('JAKARTA')
        ->and($qris->postalCode())->toBe('12345')
        ->and($qris->merchantCategoryCode())->toBe('5812')
        ->and($qris->currency())->toBe('360')
        ->and($qris->countryCode())->toBe('ID')
        ->and($qris->amount())->toBeNull();
});

it('serializes back to the identical payload', function (): void {
    expect(QrisPayload::parse(staticQris())->toString())->toBe(staticQris());
});

it('ignores surrounding whitespace', function (): void {
    expect(QrisPayload::parse('  '.staticQris()."\n")->merchantName())->toBe('WARUNG MAKAN TMC');
});

it('rejects a payload with a wrong CRC', function (): void {
    $payload = substr(staticQris(), 0, -4).'0000';

    expect(fn () => QrisPayload::parse($payload))
        ->toThrow(InvalidQrisException::class, 'Invalid CRC');
});

it('accepts a wrong CRC when verification is disabled', function (): void {
    $payload = substr(staticQris(), 0, -4).'0000';

    expect(QrisPayload::parse($payload, verifyCrc: false)->merchantName())->toBe('WARUNG MAKAN TMC');
});

it('rejects malformed payloads', function (string $payload): void {
    expect(fn () => QrisPayload::parse($payload, verifyCrc: false))
        ->toThrow(InvalidQrisException::class);
})->with([
    'empty' => '',
    'not qris' => 'https://example.com',
    'truncated' => substr(staticQris(), 0, 40),
    'missing format indicator' => qris(['01' => '11', '59' => 'TOKO']),
    'missing crc' => '000201010211',
    'non numeric length' => '00020101AB116304FFFF',
]);

it('rejects duplicate tags', function (): void {
    $payload = '000201010211'.'5901A'.'5901B'.'6304';
    $payload .= Crc16::compute($payload);

    expect(fn () => QrisPayload::parse($payload))
        ->toThrow(InvalidQrisException::class, 'Duplicate data object 59');
});

it('handles multibyte merchant names', function (): void {
    $payload = qris(['00' => '01', '01' => '11', '58' => 'ID', '59' => 'KEDAI KOPI ☕', '60' => 'BANDUNG']);

    $qris = QrisPayload::parse($payload);

    expect($qris->merchantName())->toBe('KEDAI KOPI ☕')
        ->and($qris->toString())->toBe($payload);
});
