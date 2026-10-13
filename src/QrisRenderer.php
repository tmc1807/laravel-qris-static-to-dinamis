<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererInterface;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * Renders a QRIS payload as an SVG or PNG image. Instances are immutable.
 */
final class QrisRenderer
{
    public const FORMATS = ['svg', 'png'];

    private const ERROR_CORRECTION_LEVELS = ['L', 'M', 'Q', 'H'];

    public function __construct(
        private int $size = 300,
        private int $margin = 2,
        private string $errorCorrection = 'M',
        private string $foreground = '#000000',
        private string $background = '#FFFFFF',
    ) {
        $this->assertSize($size);
        $this->assertMargin($margin);
        $this->errorCorrection = $this->normalizeErrorCorrection($errorCorrection);
        $this->foreground = $this->normalizeColor($foreground);
        $this->background = $this->normalizeColor($background);
    }

    /**
     * Image width and height in pixels.
     */
    public function size(int $size): self
    {
        $this->assertSize($size);

        $clone = clone $this;
        $clone->size = $size;

        return $clone;
    }

    /**
     * Quiet zone around the code, in modules.
     */
    public function margin(int $margin): self
    {
        $this->assertMargin($margin);

        $clone = clone $this;
        $clone->margin = $margin;

        return $clone;
    }

    /**
     * Error correction level: L, M, Q or H.
     */
    public function errorCorrection(string $level): self
    {
        $clone = clone $this;
        $clone->errorCorrection = $this->normalizeErrorCorrection($level);

        return $clone;
    }

    /**
     * Hex colors such as "#000000" or "#fff".
     */
    public function color(string $foreground, ?string $background = null): self
    {
        $clone = clone $this;
        $clone->foreground = $this->normalizeColor($foreground);

        if ($background !== null) {
            $clone->background = $this->normalizeColor($background);
        }

        return $clone;
    }

    public function svg(string $payload): string
    {
        $style = new RendererStyle($this->size, $this->margin, fill: $this->fill());

        return $this->write(new ImageRenderer($style, new SvgImageBackEnd), $payload);
    }

    public function png(string $payload): string
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('Rendering PNG requires the PHP gd extension. Use svg() instead.');
        }

        return $this->write(new GDLibRenderer($this->size, $this->margin, 'png', 9, $this->fill()), $payload);
    }

    public function render(string $payload, string $format = 'svg'): string
    {
        return match ($this->normalizeFormat($format)) {
            'svg' => $this->svg($payload),
            'png' => $this->png($payload),
        };
    }

    /**
     * A data URI ready for an <img src="..."> attribute.
     */
    public function dataUri(string $payload, string $format = 'svg'): string
    {
        $format = $this->normalizeFormat($format);

        return 'data:'.self::mimeType($format).';base64,'.base64_encode($this->render($payload, $format));
    }

    public static function mimeType(string $format): string
    {
        return match (strtolower($format)) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            default => throw new InvalidArgumentException("Unsupported image format [{$format}]."),
        };
    }

    private function write(RendererInterface $renderer, string $payload): string
    {
        // Plain ASCII payloads skip the UTF-8 ECI header, which some scanners mishandle.
        $encoding = mb_check_encoding($payload, 'ASCII') ? 'ISO-8859-1' : 'UTF-8';

        return (new Writer($renderer))->writeString(
            $payload,
            $encoding,
            ErrorCorrectionLevel::valueOf($this->errorCorrection),
        );
    }

    private function fill(): Fill
    {
        return Fill::uniformColor($this->rgb($this->background), $this->rgb($this->foreground));
    }

    private function rgb(string $hex): Rgb
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return new Rgb($red, $green, $blue);
    }

    private function normalizeColor(string $color): string
    {
        $color = ltrim(trim($color), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $color)) {
            $color = $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException("Invalid hex color [{$color}].");
        }

        return '#'.strtoupper($color);
    }

    private function normalizeErrorCorrection(string $level): string
    {
        $level = strtoupper(trim($level));

        if (! in_array($level, self::ERROR_CORRECTION_LEVELS, true)) {
            throw new InvalidArgumentException("Invalid error correction level [{$level}]. Use L, M, Q or H.");
        }

        return $level;
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("Unsupported image format [{$format}]. Use svg or png.");
        }

        return $format;
    }

    private function assertSize(int $size): void
    {
        if ($size < 50 || $size > 4000) {
            throw new InvalidArgumentException('Image size must be between 50 and 4000 pixels.');
        }
    }

    private function assertMargin(int $margin): void
    {
        if ($margin < 0 || $margin > 20) {
            throw new InvalidArgumentException('Margin must be between 0 and 20 modules.');
        }
    }
}
