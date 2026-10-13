<?php

use Tmc1807\LaravelQrisStaticToDinamis\Crc16;

it('matches the CRC-16/CCITT-FALSE check value', function (): void {
    expect(Crc16::compute('123456789'))->toBe('29B1');
});

it('pads the checksum to four uppercase hex characters', function (): void {
    expect(Crc16::compute(''))->toBe('FFFF')
        ->and(Crc16::compute('000201010211'))->toMatch('/^[0-9A-F]{4}$/');
});
