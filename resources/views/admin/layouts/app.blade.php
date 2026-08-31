<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — Keuangan Kita</title>
    <link href="{{ asset('master/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('master/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="admin-app">
        <nav class="navbar navbar-custom navbar-fixed-top admin-navbar" role="navigation">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="admin-nav-toggle" aria-controls="admin-sidebar" aria-expanded="false">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="{{ route('admin.dashboard') }}"><span>Admin</span> Keuangan</a>
                </div>
            </div>
        </nav>

        <div class="admin-backdrop" id="admin-backdrop"></div>

        <aside id="admin-sidebar" class="admin-sidebar">
            <div class="admin-sidebar-profile">
                <div class="name">{{ auth()->user()?->name }}</div>
                <div class="role">Administrator</div>
            </div>
            <ul class="nav menu admin-nav">
                <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin.dashboard') }}"><em class="fa fa-dashboard">&nbsp;</em> Dashboard</a>
                </li>
                <li class="{{ request()->routeIs('admin.finance-entities.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.finance-entities.index') }}"><em class="fa fa-building">&nbsp;</em> Finance Entities</a>
                </li>
                <li class="{{ request()->routeIs('admin.portal-access.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.portal-access.index') }}"><em class="fa fa-link">&nbsp;</em> Portal Access</a>
                </li>
                <li class="{{ request()->routeIs('admin.plantation-integrations.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.plantation-integrations.index') }}"><em class="fa fa-leaf">&nbsp;</em> Management Kebun</a>
                </li>
                <li class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.reports.index') }}"><em class="fa fa-bar-chart">&nbsp;</em> Laporan Konsolidasi</a>
                </li>
                <li class="{{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.audit-logs.index') }}"><em class="fa fa-list-alt">&nbsp;</em> Audit Logs</a>
                </li>
                <li class="admin-logout">
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-default btn-sm btn-block">
                            <i class="fa fa-sign-out"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </aside>

        <main class="admin-main">
            <div class="admin-main-inner">
                <div class="panel panel-default">
                    <div class="panel-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(session('danger'))
                            <div class="alert alert-danger">{{ session('danger') }}</div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif

                        @yield('content')
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="{{ asset('master/js/jquery-1.11.1.min.js') }}"></script>
    <script src="{{ asset('master/js/bootstrap.min.js') }}"></script>
    <script>
        $(function () {
            var $app = $('.admin-app');
            var $toggle = $('.admin-nav-toggle');

            function setOpen(open) {
                $app.toggleClass('is-sidebar-open', open);
                $toggle.attr('aria-expanded', open ? 'true' : 'false');
            }

            $toggle.on('click', function (e) {
                e.preventDefault();
                setOpen(! $app.hasClass('is-sidebar-open'));
            });

            $('#admin-backdrop').on('click', function () {
                setOpen(false);
            });

            $(window).on('resize', function () {
                if (window.innerWidth >= 992) {
                    setOpen(false);
                }
            });

            $('.admin-sidebar a').on('click', function () {
                if (window.innerWidth < 992) {
                    setOpen(false);
                }
            });
        });
    </script>
    <script src="{{ asset('js/rupiah-input.js') }}"></script>
    @stack('scripts')
</body>
</html>
