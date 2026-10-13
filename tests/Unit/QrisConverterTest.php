<?php

use Tmc1807\LaravelQrisStaticToDinamis\Crc16;
use Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException;
use Tmc1807\LaravelQrisStaticToDinamis\Fee;
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;
use Tmc1807\LaravelQrisStaticToDinamis\QrisPayload;

it('converts a static payload into a dynamic one with an amount', function (): void {
    $dynamic = (new QrisConverter)->toDynamic(staticQris(), 50000);

    $qris = QrisPayload::parse($dynamic);

    expect($qris->isDynamic())->toBeTrue()
        ->and($qris->amount())->toBe('50000')
        ->and($dynamic)->toContain('010212')
        ->and($dynamic)->toContain('540550000'.'5802ID')
        ->and($dynamic)->not->toContain('010211');
});

it('keeps every other data object untouched', function (): void {
    $static = QrisPayload::parse(staticQris())->tags();
    $dynamic = QrisPayload::parse((new QrisConverter)->toDynamic(staticQris(), 15000))->tags();

    unset($static['01'], $static['63'], $dynamic['01'], $dynamic['54'], $dynamic['63']);

    expect($dynamic)->toBe($static);
});

it('produces a payload with a valid CRC', function (): void {
    $dynamic = (new QrisConverter)->toDynamic(staticQris(), 10000);

    expect(substr($dynamic, -8, 4))->toBe('6304')
        ->and(substr($dynamic, -4))->toBe(Crc16::compute(substr($dynamic, 0, -4)));
});

it('formats amounts', function (int|float|string $input, string $expected): void {
    $qris = QrisPayload::parse((new QrisConverter)->toDynamic(staticQris(), $input));

    expect($qris->amount())->toBe($expected);
})->with([
    [1, '1'],
    [50000, '50000'],
    ['75000', '75000'],
    [' 2500 ', '2500'],
    [12500.5, '12500.5'],
    ['10000.00', '10000'],
    ['99.999', '100'],
]);

it('rejects invalid amounts', function (mixed $amount): void {
    expect(fn () => (new QrisConverter)->toDynamic(staticQris(), $amount))
        ->toThrow(InvalidArgumentException::class);
})->with([0, -100, '0.001', 'abc', '1e5', '1,000', '99999999999999']);

it('replaces the amount of an already dynamic payload', function (): void {
    $converter = new QrisConverter;

    $dynamic = $converter->toDynamic($converter->toDynamic(staticQris(), 10000, Fee::fixed(500)), 20000);
    $qris = QrisPayload::parse($dynamic);

    expect($qris->amount())->toBe('20000')
        ->and($qris->has('55'))->toBeFalse()
        ->and($qris->has('56'))->toBeFalse();
});

it('adds a tip prompt', function (): void {
    $qris = QrisPayload::parse((new QrisConverter)->toDynamic(staticQris(), 10000, Fee::prompt()));

    expect($qris->get('55'))->toBe('01')
        ->and($qris->has('56'))->toBeFalse()
        ->and($qris->has('57'))->toBeFalse();
});

it('adds a fixed convenience fee', function (): void {
    $qris = QrisPayload::parse((new QrisConverter)->toDynamic(staticQris(), 10000, Fee::fixed(1000)));

    expect($qris->get('55'))->toBe('02')
        ->and($qris->get('56'))->toBe('1000');
});

it('adds a percentage convenience fee', function (): void {
    $qris = QrisPayload::parse((new QrisConverter)->toDynamic(staticQris(), 10000, Fee::percent(0.7)));

    expect($qris->get('55'))->toBe('03')
        ->and($qris->get('57'))->toBe('0.7');
});

it('rejects invalid fee percentages', function (mixed $percentage): void {
    expect(fn () => Fee::percent($percentage))->toThrow(InvalidArgumentException::class);
})->with([0, 100, -1, 0.001, 'abc']);

it('converts a dynamic payload back to static', function (): void {
    $converter = new QrisConverter;

    $static = $converter->toStatic($converter->toDynamic(staticQris(), 10000, Fee::percent(1)));

    expect($static)->toBe(staticQris());
});

it('orders tags ascending even when the source is not ordered', function (): void {
    $unordered = qris(['00' => '01', '59' => 'TOKO', '01' => '11', '58' => 'ID', '53' => '360']);

    $dynamic = (new QrisConverter)->toDynamic($unordered, 5000);

    expect(array_map('strval', array_keys(QrisPayload::parse($dynamic)->tags())))
        ->toBe(['00', '01', '53', '54', '58', '59', '63']);
});

it('refuses to convert a payload with an invalid CRC', function (): void {
    $payload = substr(staticQris(), 0, -4).'ABCD';

    expect(fn () => (new QrisConverter)->toDynamic($payload, 1000))
        ->toThrow(InvalidQrisException::class);

    expect(QrisPayload::parse((new QrisConverter(verifyCrc: false))->toDynamic($payload, 1000))->amount())
        ->toBe('1000');
});

it('validates payloads', function (): void {
    $converter = new QrisConverter;

    expect($converter->isValid(staticQris()))->toBeTrue()
        ->and($converter->isValid('hello'))->toBeFalse()
        ->and($converter->isValid(substr(staticQris(), 0, -1).'X'))->toBeFalse();
});
