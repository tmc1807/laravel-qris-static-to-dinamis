<?php

namespace Tmc1807\LaravelQrisStaticToDinamis\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Tmc1807\LaravelQrisStaticToDinamis\Fee;
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;

/**
 * <x-qris::image :payload="$static" :amount="$order->total" />
 */
final class Image extends Component
{
    public string $payload;

    public function __construct(
        string $payload,
        int|float|string|null $amount = null,
        ?Fee $fee = null,
        public ?int $size = null,
        public ?string $format = null,
        public string $alt = 'QRIS',
    ) {
        $converter = app(QrisConverter::class);

        $this->payload = $amount === null ? $payload : $converter->toDynamic($payload, $amount, $fee);
        $this->format ??= config('qris.image.format', 'svg');
    }

    public function render(): View
    {
        $converter = app(QrisConverter::class);

        return view('qris::components.image', [
            'inline' => strtolower($this->format) === 'svg',
            'svg' => fn (): string => $converter->svg($this->payload, $this->size),
            'src' => fn (): string => $converter->dataUri($this->payload, $this->format, $this->size),
            'pixels' => $this->size ?? (int) config('qris.image.size', 300),
        ]);
    }
}
