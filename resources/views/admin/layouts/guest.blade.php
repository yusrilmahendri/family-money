<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Masuk Admin' }} — Arusku</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f7fb;
            color: #2c3a4a;
            font-family: "Montserrat", "Helvetica Neue", Helvetica, Arial, sans-serif;
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            padding: 48px 16px 32px;
            box-sizing: border-box;
        }
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            color: inherit;
            text-decoration: none;
        }
        .login-brand:hover,
        .login-brand:focus {
            color: inherit;
            text-decoration: none;
        }
        .login-wordmark {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #163f78;
        }
        .login-card {
            background: #fff;
            border: 1px solid #e5edf5;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 10px 28px rgba(22, 63, 120, 0.08);
            min-width: 0;
        }
        .login-card .form-control {
            height: 42px;
            border-radius: 8px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .login-card h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px;
            color: #163f78;
        }
        .login-card .sub {
            color: #5b6b7c;
            font-size: 13px;
            margin-bottom: 22px;
        }
        .btn-login {
            width: 100%;
            border-radius: 24px;
            font-weight: 600;
            padding: 10px 0;
            margin-top: 8px;
            background: #1e5aa8;
            border-color: #1e5aa8;
        }
        .btn-login:hover,
        .btn-login:focus {
            background: #163f78;
            border-color: #163f78;
        }
        .login-foot {
            text-align: center;
            color: #5b6b7c;
            margin-top: 24px;
            font-size: 12px;
        }
        .login-foot a {
            color: #1e5aa8;
        }
        @media (max-width: 575.98px) {
            .login-wrap { padding: 28px 14px 24px; }
            .login-card { padding: 24px 16px; }
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <a href="{{ route('home') }}" class="login-brand">
            <span aria-hidden="true">
                <svg viewBox="0 0 40 40" width="36" height="36" focusable="false">
                    <rect width="40" height="40" rx="12" fill="#1e5aa8"/>
                    <path d="M8 24c4.5-7 8.5-7 13 0s8.5 7 13 0" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>
                    <path d="M8 16c4.5-7 8.5-7 13 0s8.5 7 13 0" fill="none" stroke="#b9d4f3" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="login-wordmark">ARUSKU</span>
        </a>
        @yield('content')
        <div class="login-foot"><a href="{{ route('home') }}">Kembali ke Portal Arusku</a></div>
    </div>
    <script src="{{ asset('master/js/jquery-1.11.1.min.js') }}"></script>
    <script src="{{ asset('master/js/bootstrap.min.js') }}"></script>
</body>
</html>
