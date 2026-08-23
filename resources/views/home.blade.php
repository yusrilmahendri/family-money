<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Keuangan Kita' }}</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f7fb; font-family: 'Montserrat', sans-serif; }
        .box { max-width: 520px; margin: 80px auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    </style>
</head>
<body>
    <div class="box">
        <h3 style="margin-top:0;">Keuangan Kita</h3>
        <p>Keuangan keluarga dan usaha memakai tautan privat per FinanceEntity. Portal konteks lama <code>/apps</code> (PRIBADI / USAHA_KEBUN) sudah tidak dipakai.</p>
        @if(session('danger'))
            <div class="alert alert-danger">{{ session('danger') }}</div>
        @endif
        <p>
            <a href="{{ route('admin.login') }}" class="btn btn-primary">Admin Login</a>
        </p>
        <p class="text-muted" style="margin-bottom:0; font-size:13px;">
            Pengguna keluarga/usaha masuk lewat tautan <code>/access/{token}</code> yang diterbitkan admin.
        </p>
    </div>
</body>
</html>
