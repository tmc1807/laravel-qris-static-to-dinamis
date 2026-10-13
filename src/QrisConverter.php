<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use Illuminate\Http\Response;

final class QrisConverter
{
    private QrisRenderer $renderer;

    public function __construct(private bool $verifyCrc = true, ?QrisRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new QrisRenderer;
    }

    /**
     * Convert a static (or dynamic) QRIS payload into a dynamic one with a fixed amount.
     */
    public function toDynamic(string $payload, int|float|string $amount, ?Fee $fee = null): string
    {
        $qris = QrisPayload::parse($payload, $this->verifyCrc)
            ->without(
                QrisPayload::TAG_AMOUNT,
                QrisPayload::TAG_TIP_INDICATOR,
                QrisPayload::TAG_FEE_FIXED,
                QrisPayload::TAG_FEE_PERCENTAGE,
            )
            ->with(QrisPayload::TAG_INITIATION_METHOD, QrisPayload::DYNAMIC)
            ->with(QrisPayload::TAG_AMOUNT, Amount::format($amount));

        if ($fee !== null) {
            $qris = $fee->applyTo($qris);
        }

        return $qris->toString();
    }

    /**
     * Strip amount and fee data, turning a payload back into a static QRIS.
     */
    public function toStatic(string $payload): string
    {
        return QrisPayload::parse($payload, $this->verifyCrc)
            ->without(
                QrisPayload::TAG_AMOUNT,
                QrisPayload::TAG_TIP_INDICATOR,
                QrisPayload::TAG_FEE_FIXED,
                QrisPayload::TAG_FEE_PERCENTAGE,
            )
            ->with(QrisPayload::TAG_INITIATION_METHOD, QrisPayload::STATIC)
            ->toString();
    }

    public function parse(string $payload): QrisPayload
    {
        return QrisPayload::parse($payload, $this->verifyCrc);
    }

    public function isValid(string $payload): bool
    {
        try {
            QrisPayload::parse($payload, true);

            return true;
        } catch (Exceptions\InvalidQrisException) {
            return false;
        }
    }

    /**
     * The image renderer, for custom size, colors or error correction per call.
     */
    public function image(): QrisRenderer
    {
        return $this->renderer;
    }

    public function svg(string $payload, ?int $size = null): string
    {
        return $this->rendererFor($payload, $size)->svg($payload);
    }

    public function png(string $payload, ?int $size = null): string
    {
        return $this->rendererFor($payload, $size)->png($payload);
    }

    public function dataUri(string $payload, string $format = 'svg', ?int $size = null): string
    {
        return $this->rendererFor($payload, $size)->dataUri($payload, $format);
    }

    /**
     * An HTTP response serving the QR image, e.g. from a route like /orders/{order}/qris.png.
     */
    public function response(string $payload, string $format = 'png', ?int $size = null): Response
    {
        $image = $this->rendererFor($payload, $size)->render($payload, $format);

        return new Response($image, 200, [
            'Content-Type' => QrisRenderer::mimeType($format),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function rendererFor(string $payload, ?int $size): QrisRenderer
    {
        // Refuse to draw strings that are not QRIS, so typos never reach a customer.
        QrisPayload::parse($payload, $this->verifyCrc);

        return $size === null ? $this->renderer : $this->renderer->size($size);
    }
}
