@extends('welcome')

@section('content')
<div class="container mt-4" style="margin-top: 25px;">

    {{-- Banner Konteks + Saldo Global --}}
    <div class="row" style="margin-top: 10px;">
        <div class="col-xs-12">
            <div class="panel" style="border-left: 5px solid #28a745; background:#f5fdf7;">
                <div class="panel-body" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
                    <div>
                        <span class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">
                            <i class="fa fa-leaf"></i> Aplikasi Aktif
                        </span>
                        <div style="font-size:20px; font-weight:700; color:#28a745;">
                            {{ $financeContextLabel }}
                        </div>
                        <small class="text-muted">Pemasukan hasil usaha, biaya operasional &amp; laba/rugi</small>
                    </div>
                    <div style="text-align:right;">
                        <span class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px;">
                            <i class="fa fa-globe"></i> Sisa Saldo Global (Shared)
                        </span>
                        <div style="font-size:24px; font-weight:800; color:{{ $totalSaldo < 0 ? '#d9534f' : '#28a745' }}; word-break:break-all;">
                            Rp {{ number_format($totalSaldo, 0, ',', '.') }}
                        </div>
                        <small class="text-muted" style="display:block;">
                            Total masuk: <strong>Rp {{ number_format($saldoMasuk, 0, ',', '.') }}</strong>
                            &minus; Uang keluar: <strong>Rp {{ number_format($saldoKeluar, 0, ',', '.') }}</strong>
                        </small>
                        <a href="{{ route('apps.index') }}" class="btn btn-default btn-xs" style="margin-top:4px;">
                            <i class="fa fa-exchange"></i> Ganti aplikasi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Rincian Saldo Global (event-based) --}}
    @isset($saldoBreakdown)
    <div class="row" style="margin-top:-5px;">
        <div class="col-xs-12">
            <details class="panel panel-default" style="margin-bottom:15px;">
                <summary style="cursor:pointer; padding:10px 15px; font-size:13px; color:#666;">
                    <i class="fa fa-list-ul"></i> Rincian saldo global (uang masuk &amp; keluar)
                </summary>
                <div class="panel-body" style="border-top:1px solid #eee;">
                    <div class="row" style="font-size:13px;">
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Total Pemasukan</span><br><strong class="text-success">Rp {{ number_format($saldoBreakdown['income'], 0, ',', '.') }}</strong></div>
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Transaksi (cash out)</span><br><strong class="text-danger">Rp {{ number_format($saldoBreakdown['transactions'], 0, ',', '.') }}</strong></div>
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Pembayaran Utang</span><br><strong class="text-danger">Rp {{ number_format($saldoBreakdown['debt_payments'], 0, ',', '.') }}</strong></div>
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Kontribusi Goal</span><br><strong class="text-danger">Rp {{ number_format($saldoBreakdown['goal_contributions'], 0, ',', '.') }}</strong></div>
                    </div>
                    <div class="row" style="font-size:13px; margin-top:10px;">
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Biaya Operasional (posted)</span><br><strong class="text-danger">Rp {{ number_format($saldoBreakdown['operational'], 0, ',', '.') }}</strong></div>
                        <div class="col-xs-6 col-md-3"><span class="text-muted">Total Uang Keluar</span><br><strong class="text-danger">Rp {{ number_format($saldoBreakdown['cash_out'], 0, ',', '.') }}</strong></div>
                        <div class="col-xs-12 col-md-6"><span class="text-muted">Saldo Global (income &minus; cash out)</span><br><strong class="{{ $saldoBreakdown['saldo'] < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format($saldoBreakdown['saldo'], 0, ',', '.') }}</strong></div>
                    </div>
                    <p class="text-muted" style="margin:10px 0 0; font-size:12px;">
                        Anggaran, utang, &amp; goal yang baru dibuat <strong>tidak</strong> mengurangi saldo. Saldo berkurang hanya saat pembayaran/realisasi.
                    </p>
                </div>
            </details>
        </div>
    </div>
    @endisset

    {{-- Widget utama USAHA --}}
    <div class="row g-3">
        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Pemasukan Usaha Bulan Ini</h6>
                <h3 class="text-success" style="font-weight:700; word-break:break-all;">
                    Rp {{ number_format($pemasukanBulanIni, 0, ',', '.') }}
                </h3>
                <small class="text-muted">Total: Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</small>
            </div></div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Biaya Operasional Bulan Ini</h6>
                <h3 class="text-danger" style="font-weight:700; word-break:break-all;">
                    Rp {{ number_format($biayaBulanIni, 0, ',', '.') }}
                </h3>
                <small class="text-muted">Total: Rp {{ number_format($totalBiaya, 0, ',', '.') }}</small>
            </div></div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Laba / Rugi Bulan Ini</h6>
                <h3 class="{{ $labaBulanIni < 0 ? 'text-danger' : 'text-success' }}" style="font-weight:700; word-break:break-all;">
                    Rp {{ number_format($labaBulanIni, 0, ',', '.') }}
                </h3>
                <small class="text-muted">Pemasukan − Biaya operasional</small>
            </div></div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:15px;">
            <div class="card shadow-sm border-0"><div class="card-body">
                <h6 class="text-muted">Margin Bulan Ini</h6>
                <h3 class="text-primary" style="font-weight:700;">
                    {{ $pemasukanBulanIni > 0 ? number_format($labaBulanIni / $pemasukanBulanIni * 100, 1, ',', '.') : '0' }}%
                </h3>
                <small class="text-muted">Laba / Pemasukan</small>
            </div></div>
        </div>
    </div>

    <div class="row">
        {{-- Chart cashflow usaha --}}
        <div class="col-xs-12 col-md-7" style="margin-bottom:15px;">
            <div class="panel"><div class="panel-body">
                <h5 style="margin-top:0; font-weight:700;">Pemasukan vs Biaya (12 Bulan)</h5>
                <canvas id="chartCashflow" height="150"></canvas>
            </div></div>
        </div>

        {{-- Top biaya --}}
        <div class="col-xs-12 col-md-5" style="margin-bottom:15px;">
            <div class="panel"><div class="panel-body">
                <h5 style="margin-top:0; font-weight:700;">Top Biaya Operasional Bulan Ini</h5>
                @forelse($topBiaya as $b)
                    <div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding:6px 0;">
                        <span>{{ $b['name'] }} <small class="text-muted">({{ $b['category'] }})</small></span>
                        <strong class="text-danger">Rp {{ number_format($b['amount'], 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <p class="text-muted">Belum ada biaya operasional bulan ini. <a href="{{ route('operational.index') }}">Tambah biaya</a>.</p>
                @endforelse
            </div></div>
        </div>
    </div>

    {{-- Laba/rugi per jenis usaha --}}
    @if($labaPerUsaha->count())
    <div class="row">
        <div class="col-xs-12" style="margin-bottom:15px;">
            <div class="panel"><div class="panel-body">
                <h5 style="margin-top:0; font-weight:700;">Laba / Rugi per Jenis Usaha (Bulan Ini)</h5>
                <div class="table-responsive">
                    <table class="table table-striped" style="margin-bottom:0;">
                        <thead><tr><th>Jenis Usaha</th><th class="text-right">Pendapatan</th><th class="text-right">Biaya</th><th class="text-right">Laba/Rugi</th></tr></thead>
                        <tbody>
                            @foreach($labaPerUsaha as $r)
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="text-right">Rp {{ number_format($r['pendapatan'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($r['biaya'], 0, ',', '.') }}</td>
                                    <td class="text-right {{ $r['laba'] < 0 ? 'text-danger' : 'text-success' }}">
                                        <strong>Rp {{ number_format($r['laba'], 0, ',', '.') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('chartCashflow');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_column($cashflowBulanan, 'bulan')) !!},
            datasets: [
                { label: 'Pemasukan', data: {!! json_encode(array_column($cashflowBulanan, 'pemasukan')) !!}, backgroundColor: '#28a745' },
                { label: 'Biaya', data: {!! json_encode(array_column($cashflowBulanan, 'biaya')) !!}, backgroundColor: '#d9534f' }
            ]
        },
        options: { responsive: true }
    });
})();
</script>
@endpush
