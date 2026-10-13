<?php

namespace Tmc1807\LaravelQrisStaticToDinamis\Facades;

use Illuminate\Support\Facades\Facade;
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;

/**
 * @method static string toDynamic(string $payload, int|float|string $amount, ?\Tmc1807\LaravelQrisStaticToDinamis\Fee $fee = null)
 * @method static string toStatic(string $payload)
 * @method static \Tmc1807\LaravelQrisStaticToDinamis\QrisPayload parse(string $payload)
 * @method static bool isValid(string $payload)
 * @method static \Tmc1807\LaravelQrisStaticToDinamis\QrisRenderer image()
 * @method static string svg(string $payload, ?int $size = null)
 * @method static string png(string $payload, ?int $size = null)
 * @method static string dataUri(string $payload, string $format = 'svg', ?int $size = null)
 * @method static \Illuminate\Http\Response response(string $payload, string $format = 'png', ?int $size = null)
 *
 * @see QrisConverter
 */
final class Qris extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QrisConverter::class;
    }
}
