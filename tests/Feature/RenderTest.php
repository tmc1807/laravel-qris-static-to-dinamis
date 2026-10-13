<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException;
use Tmc1807\LaravelQrisStaticToDinamis\Facades\Qris;
use Tmc1807\LaravelQrisStaticToDinamis\Fee;
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;
use Tmc1807\LaravelQrisStaticToDinamis\QrisPayload;
use Tmc1807\LaravelQrisStaticToDinamis\QrisRenderer;

it('renders through the facade using configured defaults', function (): void {
    config()->set('qris.image.size', 220);
    app()->forgetInstance(QrisRenderer::class);
    app()->forgetInstance(QrisConverter::class);
    Qris::clearResolvedInstances();

    $dynamic = Qris::toDynamic(staticQris(), 30000);

    expect(getimagesizefromstring(Qris::png($dynamic))[0])->toBe(220)
        ->and(getimagesizefromstring(Qris::png($dynamic, 500))[0])->toBe(500)
        ->and(Qris::svg($dynamic))->toContain('width="220"')
        ->and(Qris::dataUri($dynamic, 'png'))->toStartWith('data:image/png;base64,')
        ->and(decodeQr(Qris::png($dynamic)))->toBe($dynamic);
});

it('supports per call customization via image()', function (): void {
    $svg = Qris::image()->size(180)->color('#003366')->svg(staticQris());

    expect($svg)->toContain('width="180"')->and($svg)->toContain('fill="#003366"');
});

it('refuses to render strings that are not QRIS', function (): void {
    expect(fn () => Qris::png('https://example.com'))->toThrow(InvalidQrisException::class)
        ->and(fn () => Qris::svg(substr(staticQris(), 0, -4).'0000'))->toThrow(InvalidQrisException::class);
});

it('returns an image response', function (): void {
    $response = Qris::response(staticQris());

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(decodeQr($response->getContent()))->toBe(staticQris());

    expect(Qris::response(staticQris(), 'svg')->headers->get('Content-Type'))->toBe('image/svg+xml');
});

it('serves the image from a route', function (): void {
    $static = staticQris();

    app('router')->get('/qris.png', fn () => Qris::response(Qris::toDynamic($static, 12000)));

    $response = $this->get('/qris.png')->assertOk()->assertHeader('Content-Type', 'image/png');

    expect(QrisPayload::parse(decodeQr($response->getContent()))->amount())->toBe('12000');
});

it('renders the blade component as inline svg', function (): void {
    $html = Blade::render('<x-qris::image :payload="$payload" :size="200" class="mx-auto" />', [
        'payload' => staticQris(),
    ]);

    expect($html)->toContain('<svg')
        ->and($html)->toContain('width="200"')
        ->and($html)->toContain('class="mx-auto"')
        ->and($html)->toContain('aria-label="QRIS"');
});

it('converts to dynamic inside the blade component', function (): void {
    $html = Blade::render('<x-qris::image :payload="$payload" :amount="25000" :fee="$fee" format="png" alt="Bayar" />', [
        'payload' => staticQris(),
        'fee' => Fee::fixed(1000),
    ]);

    preg_match('/src="data:image\/png;base64,([^"]+)"/', $html, $matches);

    $qris = QrisPayload::parse(decodeQr(base64_decode($matches[1])));

    expect($html)->toContain('alt="Bayar"')
        ->and($html)->toContain('width="300"')
        ->and($qris->amount())->toBe('25000')
        ->and($qris->get('56'))->toBe('1000');
});
