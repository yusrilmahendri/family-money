<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $entity->name }} — {{ $entity->name }}</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet">
    <link href="{{ asset('css/entity.css') }}" rel="stylesheet">
</head>
<body class="entity-body">
    <div class="entity-app" id="entity-app">
        @include('entity.partials.topbar')
        <div class="entity-backdrop" id="entity-backdrop"></div>
        @include('entity.partials.sidebar')

        <main class="entity-main">
            @if(session('success'))
                <div class="alert alert-success entity-flash">{{ session('success') }}</div>
            @endif
            @if(session('danger'))
                <div class="alert alert-danger entity-flash">{{ session('danger') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger entity-flash">{{ $errors->first() }}</div>
            @endif

            @hasSection('wide')
                @yield('content')
            @else
                <div class="entity-page-card">
                    @yield('content')
                </div>
            @endif
        </main>
    </div>
    <script>
        (function () {
            var app = document.getElementById('entity-app');
            var toggle = document.getElementById('entity-nav-toggle');
            var backdrop = document.getElementById('entity-backdrop');
            if (!app || !toggle) return;
            function closeNav() {
                app.classList.remove('is-nav-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
            toggle.addEventListener('click', function () {
                var open = app.classList.toggle('is-nav-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            if (backdrop) backdrop.addEventListener('click', closeNav);
        })();
    </script>
    <script src="{{ asset('js/rupiah-input.js') }}"></script>
    @stack('scripts')
</body>
</html>
