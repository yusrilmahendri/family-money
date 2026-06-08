<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih Aplikasi — Keuangan Kita</title>
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
        .portal-wrap {
            max-width: 880px;
            margin: 0 auto;
            padding: 50px 15px 30px;
        }
        .portal-head {
            text-align: center;
            color: #fff;
            margin-bottom: 35px;
        }
        .portal-head h1 { font-weight: 700; font-size: 28px; margin: 0 0 8px; }
        .portal-head p { font-size: 15px; opacity: 0.92; margin: 0; }
        .app-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px 25px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            text-align: center;
            height: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 3px solid transparent;
        }
        .app-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.22); }
        .app-card.active { border-color: #28a745; }
        .app-icon {
            width: 78px; height: 78px; line-height: 78px;
            border-radius: 50%;
            margin: 0 auto 18px;
            font-size: 34px; color: #fff;
        }
        .app-icon.pribadi { background: #6f42c1; }
        .app-icon.kebun { background: #28a745; }
        .app-card h3 { font-weight: 700; font-size: 19px; margin: 0 0 6px; color: #333; }
        .app-card .app-sub { color: #888; font-size: 13px; margin-bottom: 14px; min-height: 34px; }
        .app-card .app-stat {
            background: #f5f8fc; border-radius: 8px; padding: 8px;
            font-size: 12px; color: #555; margin-bottom: 18px;
        }
        .app-card .badge-active {
            display: inline-block; background: #28a745; color: #fff;
            font-size: 11px; padding: 3px 10px; border-radius: 12px; margin-bottom: 10px;
        }
        .btn-pick { border-radius: 24px; padding: 10px 0; font-weight: 600; width: 100%; }
        .portal-foot { text-align: center; color: #fff; opacity: 0.85; margin-top: 30px; font-size: 12px; }
        @media (max-width: 767px) {
            .app-card { margin-bottom: 20px; }
            .portal-head h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="portal-wrap">
        <div class="portal-head">
            <h1><i class="fa fa-wallet"></i> Keuangan Kita</h1>
            <p>Pilih aplikasi yang ingin Anda kelola. Saldo tetap satu (global) untuk semua aplikasi.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="row">
            {{-- Keuangan Pribadi --}}
            <div class="col-sm-6">
                <div class="app-card {{ $current === \App\Support\FinanceContext::PRIBADI ? 'active' : '' }}">
                    @if($current === \App\Support\FinanceContext::PRIBADI)
                        <div class="badge-active"><i class="fa fa-check"></i> Sedang aktif</div>
                    @endif
                    <div class="app-icon pribadi"><i class="fa fa-user"></i></div>
                    <h3>Keuangan Pribadi</h3>
                    <div class="app-sub">Pengeluaran &amp; kebutuhan pribadi (BPJS, belanja, dll.)</div>
                    <div class="app-stat">
                        {{ number_format($summary[\App\Support\FinanceContext::PRIBADI]['transaksi'] ?? 0, 0, ',', '.') }} transaksi tercatat
                    </div>
                    <form action="{{ route('apps.select') }}" method="POST">
                        @csrf
                        <input type="hidden" name="context" value="{{ \App\Support\FinanceContext::PRIBADI }}">
                        <button type="submit" class="btn btn-primary btn-pick">
                            <i class="fa fa-sign-in"></i> Masuk
                        </button>
                    </form>
                </div>
            </div>

            {{-- Keuangan Usaha Kebun --}}
            <div class="col-sm-6">
                <div class="app-card {{ $current === \App\Support\FinanceContext::USAHA_KEBUN ? 'active' : '' }}">
                    @if($current === \App\Support\FinanceContext::USAHA_KEBUN)
                        <div class="badge-active"><i class="fa fa-check"></i> Sedang aktif</div>
                    @endif
                    <div class="app-icon kebun"><i class="fa fa-leaf"></i></div>
                    <h3>Keuangan Usaha Kebun</h3>
                    <div class="app-sub">Pemasukan hasil panen, biaya operasional, anggaran usaha.</div>
                    <div class="app-stat">
                        {{ number_format($summary[\App\Support\FinanceContext::USAHA_KEBUN]['transaksi'] ?? 0, 0, ',', '.') }} transaksi tercatat
                    </div>
                    <form action="{{ route('apps.select') }}" method="POST">
                        @csrf
                        <input type="hidden" name="context" value="{{ \App\Support\FinanceContext::USAHA_KEBUN }}">
                        <button type="submit" class="btn btn-success btn-pick">
                            <i class="fa fa-sign-in"></i> Masuk
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="portal-foot">
            <strong>Catatan:</strong> Saldo bersifat global &amp; dibagi (shared). Setiap pemasukan/pengeluaran tetap mengubah saldo yang sama.
        </div>
    </div>
</body>
</html>
