<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal Arusku' }}</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet">
    <link href="{{ asset('css/portal.css') }}" rel="stylesheet">
</head>
<body class="portal-body">
    <header class="portal-header">
        <div class="portal-header-inner">
            <a href="{{ route('home') }}" class="portal-brand">
                <span class="portal-logo" aria-hidden="true">
                    <svg viewBox="0 0 40 40" width="36" height="36" focusable="false">
                        <rect width="40" height="40" rx="12" fill="#1e5aa8"/>
                        <path d="M8 24c4.5-7 8.5-7 13 0s8.5 7 13 0" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>
                        <path d="M8 16c4.5-7 8.5-7 13 0s8.5 7 13 0" fill="none" stroke="#b9d4f3" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="portal-wordmark">ARUSKU</span>
            </a>
            <div class="portal-header-end">
                @if (! empty($accessName))
                    <div class="portal-header-access" title="{{ $accessName }}">
                        <i class="fa fa-user-circle" aria-hidden="true"></i>
                        <span>{{ $accessName }}</span>
                    </div>
                @endif
                <a href="{{ url('/admin') }}" class="portal-admin-link">Admin</a>
            </div>
        </div>
    </header>

    <main class="portal-main">
        @if(session('success'))
            <div class="alert alert-success portal-flash">{{ session('success') }}</div>
        @endif
        @if(session('danger'))
            <div class="alert alert-danger portal-flash">{{ session('danger') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger portal-flash">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="portal-footer">
        <div class="portal-footer-inner">
            <p class="portal-footer-credit">Arusku · Created by @Yusril Mahendri</p>
        </div>
    </footer>
</body>
</html>
