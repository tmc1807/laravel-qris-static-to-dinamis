<?php

use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;
use Tmc1807\LaravelQrisStaticToDinamis\QrisRenderer;

it('renders a scannable PNG that decodes to the exact payload', function (): void {
    $dynamic = (new QrisConverter)->toDynamic(staticQris(), 50000);

    $png = (new QrisRenderer(size: 400))->png($dynamic);

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(decodeQr($png))->toBe($dynamic);
});

it('renders PNG at the requested size', function (): void {
    $info = getimagesizefromstring((new QrisRenderer)->size(250)->png(staticQris()));

    expect($info[0])->toBe(250)
        ->and($info[1])->toBe(250)
        ->and($info['mime'])->toBe('image/png');
});

it('renders SVG', function (): void {
    $svg = (new QrisRenderer(size: 320))->svg(staticQris());

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('width="320"')
        ->and($svg)->toContain('height="320"');
});

it('applies custom colors', function (): void {
    $renderer = (new QrisRenderer)->color('#1a2b3c', '#fff');

    expect($renderer->svg(staticQris()))->toContain('fill="#1a2b3c"')
        ->and($renderer->svg(staticQris()))->toContain('fill="#ffffff"');

    $image = imagecreatefromstring($renderer->margin(4)->png(staticQris()));
    $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));

    expect([$corner['red'], $corner['green'], $corner['blue']])->toBe([255, 255, 255]);
});

it('keeps the renderer immutable', function (): void {
    $renderer = new QrisRenderer(size: 300);
    $bigger = $renderer->size(600);

    expect($bigger)->not->toBe($renderer)
        ->and(getimagesizefromstring($renderer->png(staticQris()))[0])->toBe(300)
        ->and(getimagesizefromstring($bigger->png(staticQris()))[0])->toBe(600);
});

it('produces denser codes with higher error correction', function (): void {
    $low = (new QrisRenderer)->errorCorrection('l')->svg(staticQris());
    $high = (new QrisRenderer)->errorCorrection('H')->svg(staticQris());

    expect(strlen($high))->toBeGreaterThan(strlen($low));
});

it('builds data URIs', function (): void {
    $renderer = new QrisRenderer;

    expect($renderer->dataUri(staticQris()))->toStartWith('data:image/svg+xml;base64,')
        ->and($renderer->dataUri(staticQris(), 'PNG'))->toStartWith('data:image/png;base64,');

    $png = base64_decode(substr($renderer->dataUri(staticQris(), 'png'), strlen('data:image/png;base64,')));

    expect(decodeQr($png))->toBe(staticQris());
});

it('encodes multibyte merchant names', function (): void {
    $payload = qris(['00' => '01', '01' => '11', '58' => 'ID', '59' => 'KEDAI KOPI ☕', '60' => 'BANDUNG']);

    expect(decodeQr((new QrisRenderer(size: 400))->png($payload)))->toBe($payload);
});

it('rejects invalid options', function (Closure $build): void {
    expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'too small' => fn () => new QrisRenderer(size: 10),
    'too large' => fn () => (new QrisRenderer)->size(5000),
    'negative margin' => fn () => (new QrisRenderer)->margin(-1),
    'bad level' => fn () => (new QrisRenderer)->errorCorrection('X'),
    'bad color' => fn () => (new QrisRenderer)->color('red'),
    'bad format' => fn () => (new QrisRenderer)->render(staticQris(), 'gif'),
]);
