// app/Http/Kernel.php

protected $middleware = [
    // بقیه‌ی سراسری‌ها...

    // یکی از این دو (بسته به نسخه‌ات):
    \Fruitcake\Cors\HandleCors::class,             // اگر پکیج fruitcake/cors را داری
    // یا:
    // \Illuminate\Http\Middleware\HandleCors::class, // برای نسخه‌های جدید لاراول

    // بقیه...
];
