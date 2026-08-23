<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ?? 'Akses tidak valid' }}</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f7fb; font-family: 'Montserrat', sans-serif; }
        .box { max-width: 480px; margin: 80px auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.08); text-align: center; }
    </style>
</head>
<body>
    <div class="box">
        <h3 style="margin-top:0;">Akses tidak valid</h3>
        <p class="text-muted">Tautan ini tidak dapat digunakan.</p>
    </div>
</body>
</html>
