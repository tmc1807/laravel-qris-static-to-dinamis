<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verify CRC
    |--------------------------------------------------------------------------
    |
    | When enabled, input payloads must carry a valid CRC (tag 63) before they
    | are converted. This catches typos and truncated strings early. The CRC
    | of the generated payload is always recomputed regardless of this flag.
    |
    */
    'verify_crc' => (bool) env('QRIS_VERIFY_CRC', true),

    /*
    |--------------------------------------------------------------------------
    | QR image
    |--------------------------------------------------------------------------
    |
    | Defaults for Qris::svg(), Qris::png(), Qris::dataUri(), Qris::response()
    | and the <x-qris::image /> component. Size is in pixels, margin is the
    | quiet zone in modules. Error correction is one of L, M, Q or H; higher
    | levels survive damage better but produce denser codes. PNG output needs
    | the gd extension, SVG works everywhere.
    |
    */
    'image' => [
        'format' => env('QRIS_IMAGE_FORMAT', 'svg'),
        'size' => (int) env('QRIS_IMAGE_SIZE', 300),
        'margin' => (int) env('QRIS_IMAGE_MARGIN', 2),
        'error_correction' => env('QRIS_IMAGE_ERROR_CORRECTION', 'M'),
        'foreground' => env('QRIS_IMAGE_FOREGROUND', '#000000'),
        'background' => env('QRIS_IMAGE_BACKGROUND', '#FFFFFF'),
    ],
];
