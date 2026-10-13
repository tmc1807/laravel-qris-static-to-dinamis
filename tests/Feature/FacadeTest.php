<?php

use Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException;
use Tmc1807\LaravelQrisStaticToDinamis\Facades\Qris;
use Tmc1807\LaravelQrisStaticToDinamis\Fee;
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;

it('resolves the converter as a singleton', function (): void {
    expect(app(QrisConverter::class))->toBe(app(QrisConverter::class));
});

it('converts through the facade', function (): void {
    $dynamic = Qris::toDynamic(staticQris(), 25000, Fee::fixed(1000));

    expect(Qris::parse($dynamic)->amount())->toBe('25000')
        ->and(Qris::isValid($dynamic))->toBeTrue();
});

it('respects the verify_crc config', function (): void {
    $payload = substr(staticQris(), 0, -4).'0000';

    expect(fn () => Qris::toDynamic($payload, 1000))->toThrow(InvalidQrisException::class);

    config()->set('qris.verify_crc', false);
    app()->forgetInstance(QrisConverter::class);
    Qris::clearResolvedInstances();

    expect(Qris::parse(Qris::toDynamic($payload, 1000))->amount())->toBe('1000');
});
