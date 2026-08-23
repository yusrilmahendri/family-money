<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin Login' }} — Keuangan Kita</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #30a5ff 0%, #1f7fd1 100%);
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
            margin: 0;
        }
        .login-wrap {
            max-width: 420px;
            margin: 0 auto;
            padding: 70px 15px 30px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .login-card h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px;
            color: #333;
        }
        .login-card .sub {
            color: #888;
            font-size: 13px;
            margin-bottom: 22px;
        }
        .login-card .form-control {
            height: 42px;
            border-radius: 8px;
        }
        .btn-login {
            width: 100%;
            border-radius: 24px;
            font-weight: 600;
            padding: 10px 0;
            margin-top: 8px;
        }
        .login-foot {
            text-align: center;
            color: #fff;
            opacity: 0.85;
            margin-top: 24px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        @yield('content')
        <div class="login-foot">Admin Panel — Keuangan Kita</div>
    </div>
    <script src="{{ asset('master/js/jquery-1.11.1.min.js') }}"></script>
    <script src="{{ asset('master/js/bootstrap.min.js') }}"></script>
</body>
</html>
