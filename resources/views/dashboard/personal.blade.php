@extends('welcome')

@section('content')
<div class="container mt-4" style="margin-top: 25px;">

    {{-- Banner Konteks + Saldo Global --}}
    <div class="row" style="margin-top: 10px;">
        <div class="col-xs-12">
            <div class="panel" style="border-left: 5px solid #6f42c1; background:#faf8ff;">
                <div class="panel-body" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
                    <div>
                        <span class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">
                            <i class="fa fa-user"></i> Aplikasi Aktif
                        </span>
                        <div style="font-size:20px; font-weight:700; color:#6f42c1;">
                            {{ $financeContextLabel }}
                        </div>
                        <small class="text-muted">Pengelolaan pengeluaran &amp; kebutuhan pribadi</small>
                    </div>
                    <div style="text-align:right;">
                        <span class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">
                            <i class="fa fa-globe"></i> Saldo Global (Shared)
                        </span>
                        <div style="font-size:24px; font-weight:800; color:#28a745; word-break:break-all;">
                            Rp {{ number_format($totalSaldo, 0, ',', '.') }}
                        </div>
                        <a href="{{ route('apps.index') }}" class="btn btn-default btn-xs">
                            <i class="fa fa-exchange"></i> Ganti aplikasi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget utama PRIBADI --}}
    <div class="row g-3">
        <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Pengeluaran Pribadi Bulan Ini</h6>
                <h3 class="text-danger" style="font-weight:700; word-break:break-all;">
                    Rp {{ number_format($pengeluaranBulanIni, 0, ',', '.') }}
                </h3>
                <small class="text-muted">{{ number_format($jumlahTransaksiBulanIni, 0, ',', '.') }} transaksi bulan ini</small>
            </div></div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Total Cicilan / Bulan</h6>
                <h3 class="text-warning" style="font-weight:700; word-break:break-all;">
                    Rp {{ number_format($totalCicilan, 0, ',', '.') }}
                </h3>
                <small class="text-muted">{{ $jumlahUtang }} utang • Sisa Rp {{ number_format($totalSisaUtang, 0, ',', '.') }}</small>
            </div></div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Goals Tabungan</h6>
                <h3 class="text-primary" style="font-weight:700;">
                    {{ $goals->count() }} goal
                </h3>
                <small class="text-muted">Lihat progres di bawah</small>
            </div></div>
        </div>
    </div>

    <div class="row">
        {{-- Chart pengeluaran 12 bulan --}}
        <div class="col-xs-12 col-md-7" style="margin-bottom:15px;">
            <div class="panel"><div class="panel-body">
                <h5 style="margin-top:0; font-weight:700;">Pengeluaran Pribadi 12 Bulan</h5>
                <canvas id="chartPengeluaran" height="140"></canvas>
            </div></div>
        </div>

        {{-- Goals progress --}}
        <div class="col-xs-12 col-md-5" style="margin-bottom:15px;">
            <div class="panel"><div class="panel-body">
                <h5 style="margin-top:0; font-weight:700;">Progres Goals Tabungan</h5>
                @forelse($goals as $g)
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px;">
                            <strong>{{ $g['title'] }}</strong>
                            <span>{{ $g['pct'] }}%</span>
                        </div>
                        <div class="progress" style="height:10px; margin-bottom:4px;">
                            <div class="progress-bar progress-bar-success" style="width: {{ $g['pct'] }}%;"></div>
                        </div>
                        <small class="text-muted">
                            Rp {{ number_format($g['saved'], 0, ',', '.') }} / Rp {{ number_format($g['target'], 0, ',', '.') }}
                        </small>
                    </div>
                @empty
                    <p class="text-muted">Belum ada goal tabungan. <a href="{{ route('savings-goals.index') }}">Tambah goal</a>.</p>
                @endforelse
            </div></div>
        </div>
    </div>

    @if($lastTrans)
        <p class="text-muted" style="font-size:12px;">
            Transaksi terakhir: Rp {{ number_format($lastTrans->amount, 0, ',', '.') }} —
            {{ \Carbon\Carbon::parse($lastTrans->transaction_date)->format('d M Y') }}
        </p>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('chartPengeluaran');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_column($pengeluaranBulanan, 'bulan')) !!},
            datasets: [{
                label: 'Pengeluaran (Rp)',
                data: {!! json_encode(array_column($pengeluaranBulanan, 'total')) !!},
                backgroundColor: '#6f42c1'
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
})();
</script>
@endpush
