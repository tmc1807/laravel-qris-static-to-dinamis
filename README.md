# laravel-qris-static-to-dinamis

[![PHP](https://img.shields.io/badge/PHP-8.2+-8892BF?logo=php&logoColor=fff)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red?logo=laravel&logoColor=fff)](https://laravel.com)
[![Tests](https://github.com/tmc1807/laravel-qris-static-to-dinamis/actions/workflows/tests.yml/badge.svg)](https://github.com/tmc1807/laravel-qris-static-to-dinamis/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Ubah payload QRIS statis menjadi QRIS dinamis dengan nominal tetap di Laravel.
Pelanggan cukup scan, nominal langsung terisi di aplikasi pembayaran.

```text
...010211...5802ID...6304A1B2           (statis, nominal diisi pelanggan)
...010212...5405500005802ID...6304C3D4  (dinamis, Rp50.000)
```

## Fitur

- Parsing TLV EMVCo yang benar, bukan sekadar `str_replace`.
- Validasi CRC16 pada input dan perhitungan ulang CRC pada output.
- Menambahkan nominal (tag `54`) dan mengubah point of initiation (tag `01`) ke `12`.
- Dukungan tip dan biaya layanan: prompt, nominal tetap, atau persentase (tag `55`/`56`/`57`).
- Bisa mengganti nominal pada QRIS yang sudah dinamis, atau mengembalikannya ke statis.
- Membaca informasi merchant: nama, kota, kode pos, MCC, mata uang.
- Render gambar QR ke SVG, PNG, data URI, HTTP response, atau Blade component.
- Mendukung Laravel 11, 12, dan 13.

## Persyaratan

- PHP 8.2 atau lebih baru.
- Laravel 11, 12, atau 13.
- Extension PHP `mbstring`.
- Extension PHP `gd` (hanya untuk output PNG; SVG tidak membutuhkannya).

## Instalasi

```bash
composer require tmc1807/laravel-qris-static-to-dinamis
```

Service provider dan facade `Qris` terdaftar otomatis. Publikasikan config bila perlu:

```bash
php artisan vendor:publish --tag=qris-config
```

## Penggunaan

### Konversi ke QRIS dinamis

```php
use Tmc1807\LaravelQrisStaticToDinamis\Facades\Qris;

$static = '00020101021126670016COM.NOBUBANK.WWW...6304ABCD';

$dynamic = Qris::toDynamic($static, 50000);
```

Nominal bisa berupa `int`, `float`, atau string numerik. Desimal dibatasi dua digit,
dan angka nol di belakang koma dibuang (`'10000.00'` menjadi `10000`).

### Tip dan biaya layanan

```php
use Tmc1807\LaravelQrisStaticToDinamis\Fee;

Qris::toDynamic($static, 50000, Fee::prompt());     // pelanggan mengisi tip sendiri
Qris::toDynamic($static, 50000, Fee::fixed(1000));  // biaya layanan Rp1.000
Qris::toDynamic($static, 50000, Fee::percent(0.7)); // biaya layanan 0,7%
```

### Kembali ke statis

```php
$static = Qris::toStatic($dynamic);
```

### Membaca informasi QRIS

```php
$qris = Qris::parse($static);

$qris->merchantName();         // "WARUNG MAKAN TMC"
$qris->merchantCity();         // "JAKARTA"
$qris->postalCode();           // "12345"
$qris->merchantCategoryCode(); // "5812"
$qris->isDynamic();            // false
$qris->amount();               // null
$qris->get('26');              // data object mentah berdasarkan tag

Qris::isValid($payload);       // cek format dan CRC tanpa exception
```

### Tanpa facade

```php
use Tmc1807\LaravelQrisStaticToDinamis\QrisConverter;

$dynamic = app(QrisConverter::class)->toDynamic($static, 50000);

// atau di luar Laravel
$dynamic = (new QrisConverter)->toDynamic($static, 50000);
```

## Render gambar QR

Gambar dibuat dengan [`bacon/bacon-qr-code`](https://github.com/Bacon/BaconQrCode).
Payload selalu divalidasi dulu, jadi string yang bukan QRIS atau CRC-nya salah tidak
akan pernah tergambar.

### SVG, PNG, dan data URI

```php
$dynamic = Qris::toDynamic($static, 50000);

$svg = Qris::svg($dynamic);          // string <svg>...</svg>
$png = Qris::png($dynamic, 400);     // binary PNG 400x400 px
$uri = Qris::dataUri($dynamic);      // data:image/svg+xml;base64,...
$uri = Qris::dataUri($dynamic, 'png');

Storage::put("qris/{$order->id}.png", $png);
```

### Kustomisasi per pemanggilan

```php
Qris::image()
    ->size(500)
    ->margin(4)
    ->errorCorrection('Q')
    ->color('#0B3D91', '#FFFFFF')
    ->png($dynamic);
```

Renderer bersifat immutable: setiap method mengembalikan instance baru, sehingga
default dari config tidak ikut berubah. Validasi payload hanya dilakukan oleh method
di facade (`Qris::svg()`, `Qris::png()`, dan seterusnya), bukan oleh `Qris::image()`.

### HTTP response

```php
use Tmc1807\LaravelQrisStaticToDinamis\Facades\Qris;

Route::get('/orders/{order}/qris.png', function (Order $order) {
    return Qris::response(Qris::toDynamic(config('services.qris.static'), $order->total));
});

// SVG: Qris::response($payload, 'svg'); ukuran: Qris::response($payload, 'png', 500)
```

Response memakai header `Cache-Control: no-store, private` karena setiap QR berisi
nominal transaksi tertentu.

### Blade component

```blade
{{-- QRIS yang sudah dinamis --}}
<x-qris::image :payload="$dynamic" />

{{-- Konversi langsung di view: statis + nominal --}}
<x-qris::image :payload="$static" :amount="$order->total" :size="250" class="mx-auto" />

{{-- Dengan biaya layanan, dirender sebagai <img> PNG --}}
<x-qris::image :payload="$static" :amount="$order->total" :fee="\Tmc1807\LaravelQrisStaticToDinamis\Fee::fixed(1000)" format="png" alt="Scan untuk bayar" />
```

Format `svg` dirender inline di dalam `<div role="img">`; format `png` dirender sebagai
`<img>` dengan data URI. Atribut lain seperti `class` diteruskan ke elemen tersebut.
Tampilan bisa diubah dengan `php artisan vendor:publish --tag=qris-views`.

## Konfigurasi

```php
// config/qris.php
return [
    'verify_crc' => (bool) env('QRIS_VERIFY_CRC', true),

    'image' => [
        'format' => env('QRIS_IMAGE_FORMAT', 'svg'),        // default <x-qris::image>
        'size' => (int) env('QRIS_IMAGE_SIZE', 300),        // piksel, 50-4000
        'margin' => (int) env('QRIS_IMAGE_MARGIN', 2),      // quiet zone dalam modul
        'error_correction' => env('QRIS_IMAGE_ERROR_CORRECTION', 'M'), // L, M, Q, H
        'foreground' => env('QRIS_IMAGE_FOREGROUND', '#000000'),
        'background' => env('QRIS_IMAGE_BACKGROUND', '#FFFFFF'),
    ],
];
```

Untuk QR yang akan dicetak atau ditempel di meja kasir, gunakan margin minimal `4`
dan warna gelap di atas latar terang agar mudah dipindai.

Jika `verify_crc` aktif, payload input dengan CRC yang salah akan ditolak dengan
`InvalidQrisException`. CRC pada output selalu dihitung ulang.

## Error

| Exception | Penyebab |
| --- | --- |
| `Tmc1807\LaravelQrisStaticToDinamis\Exceptions\InvalidQrisException` | Payload bukan QRIS yang valid: format TLV rusak, terpotong, tag duplikat, tidak diawali `000201`, tidak diakhiri tag `63`, atau CRC salah. |
| `InvalidArgumentException` | Nominal atau persentase biaya tidak valid, atau opsi gambar tidak valid (ukuran, margin, warna, level koreksi, format). |
| `RuntimeException` | Meminta PNG tanpa extension `gd`. |

## Cara kerja

1. Payload di-parse menjadi data object TLV (tag 2 digit, panjang 2 digit, nilai).
2. Tag `01` diubah dari `11` (statis) menjadi `12` (dinamis).
3. Tag `54` (nominal) ditambahkan, ditambah `55`/`56`/`57` bila ada biaya. Data
   nominal dan biaya yang lama dibuang terlebih dahulu.
4. Data object disusun ulang dengan tag menaik, sehingga `54` berada sebelum `58`.
5. Tag `63` dihitung ulang memakai CRC-16/CCITT-FALSE (poly `0x1021`, init `0xFFFF`).

## Batasan penting

QRIS hasil konversi **bukan** QRIS dinamis yang diterbitkan PJP/acquirer. Nominal
memang terisi otomatis, tetapi:

- tidak ada nomor referensi transaksi unik dari acquirer;
- tidak ada callback/webhook saat pembayaran berhasil.

Verifikasi pembayaran tetap harus dilakukan lewat mutasi rekening atau aplikasi
merchant. Pastikan penggunaan ini sesuai dengan ketentuan acquirer QRIS Anda.

## Testing

```bash
composer test
composer pint -- --test
```

## Lisensi

MIT. Lihat [LICENSE](LICENSE).
