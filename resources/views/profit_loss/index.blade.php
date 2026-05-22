@extends('welcome')

@section('content')
<div class="pl-page" style="padding: 15px;">

    <h3 style="margin-top: 5px; font-size: 20px; font-weight: 700;">Laporan Laba / Rugi</h3>
    <p class="text-muted" style="margin-top: 5px;">Periode: <strong>{{ $period_label }}</strong></p>

    {{-- Filter --}}
    <form method="GET" action="{{ route('profit-loss.index') }}" class="form-inline" style="margin: 15px 0;">
        <div class="form-group" style="margin-right: 10px; margin-bottom: 10px;">
            <label style="margin-right: 5px;">Tahun:</label>
            <select name="year" class="form-control input-sm">
                @foreach($available_years as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-right: 10px; margin-bottom: 10px;">
            <label style="margin-right: 5px;">Bulan:</label>
            <select name="month" class="form-control input-sm">
                <option value="">— Sepanjang Tahun —</option>
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" @selected($month == $m)>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-right: 10px; margin-bottom: 10px;">
            <label style="margin-right: 5px;">Jenis Usaha:</label>
            <select name="category_id" class="form-control input-sm">
                <option value="">— Semua —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($category_id == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom: 10px;">
            <i class="fa fa-filter"></i> Terapkan
        </button>
        <a href="{{ route('profit-loss.index') }}" class="btn btn-default btn-sm" style="margin-bottom: 10px;">Reset</a>
    </form>

    {{-- Ringkasan --}}
    <div class="row row-eq">
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Pendapatan</h6>
                <h3 class="text-success" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_pendapatan, 0, ',', '.') }}
                </h3>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Biaya Operasional</h6>
                <h3 class="text-danger" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_biaya, 0, ',', '.') }}
                </h3>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Laba / Rugi Bersih</h6>
                <h3 class="{{ $total_laba < 0 ? 'text-danger' : 'text-primary' }}" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_laba, 0, ',', '.') }}
                </h3>
                @if($total_pendapatan > 0)
                    <small class="text-muted">Margin: {{ number_format(($total_laba/$total_pendapatan)*100, 1) }}%</small>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel per jenis usaha --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; margin-bottom: 15px;">
        <h5 style="font-weight: 700; margin-bottom: 15px; font-size: 16px;">Rincian per Jenis Usaha</h5>
        <div class="table-responsive">
            <table class="table table-striped" style="margin-bottom: 0; min-width: 600px;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Jenis Usaha</th>
                        <th class="text-right">Pendapatan</th>
                        <th class="text-right">Biaya</th>
                        <th class="text-right">Laba / Rugi</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong></td>
                            <td class="text-right text-success">Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td>
                            <td class="text-right text-danger">Rp {{ number_format($row['biaya'], 0, ',', '.') }}</td>
                            <td class="text-right">
                                <strong class="{{ $row['laba'] < 0 ? 'text-danger' : 'text-primary' }}">
                                    Rp {{ number_format($row['laba'], 0, ',', '.') }}
                                </strong>
                            </td>
                            <td class="text-right">
                                @if($row['margin'] !== null)
                                    <strong class="{{ $row['margin'] < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($row['margin'], 1) }}%
                                    </strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Belum ada data pendapatan atau biaya untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->count() > 0)
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: 700;">
                            <td>TOTAL</td>
                            <td class="text-right text-success">Rp {{ number_format($total_pendapatan, 0, ',', '.') }}</td>
                            <td class="text-right text-danger">Rp {{ number_format($total_biaya, 0, ',', '.') }}</td>
                            <td class="text-right {{ $total_laba < 0 ? 'text-danger' : 'text-primary' }}">
                                Rp {{ number_format($total_laba, 0, ',', '.') }}
                            </td>
                            <td class="text-right">
                                @if($total_pendapatan > 0)
                                    {{ number_format(($total_laba/$total_pendapatan)*100, 1) }}%
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <small class="text-muted" style="display:block; margin-top:8px;">
            <strong>Pendapatan</strong> = total pemasukan usaha. <strong>Biaya</strong> = total aktivitas anggaran (upah, bahan, dll.) pada periode tsb.
        </small>
    </div>

    {{-- Grafik tren --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
        <h5 style="font-weight: 700; margin-bottom: 15px; font-size: 16px;">Tren Bulanan {{ $year }}</h5>
        <canvas id="trendChart" height="100"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    @media (min-width: 768px) {
        .pl-page .row.row-eq { display: flex; flex-wrap: wrap; }
        .pl-page .row.row-eq > [class*='col-'] { display: flex; }
        .pl-page .row.row-eq > [class*='col-'] > .summary-card { width: 100%; flex: 1; }
    }
    @media (max-width: 767px) {
        .pl-page { padding: 10px !important; }
        .pl-page .summary-card h3 { font-size: 18px !important; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    var tren = @json($tren);
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: tren.map(function(t){ return t.label; }),
            datasets: [
                {
                    label: 'Pendapatan',
                    data: tren.map(function(t){ return t.pendapatan; }),
                    backgroundColor: 'rgba(40,167,69,0.7)',
                    borderColor: 'rgba(40,167,69,1)',
                    borderWidth: 1
                },
                {
                    label: 'Biaya',
                    data: tren.map(function(t){ return t.biaya; }),
                    backgroundColor: 'rgba(217,83,79,0.7)',
                    borderColor: 'rgba(217,83,79,1)',
                    borderWidth: 1
                },
                {
                    label: 'Laba',
                    type: 'line',
                    data: tren.map(function(t){ return t.laba; }),
                    backgroundColor: 'rgba(0,123,255,0.2)',
                    borderColor: 'rgba(0,123,255,1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var v = ctx.parsed.y || 0;
                            return ctx.dataset.label + ': Rp ' + v.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(v) { return 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : v.toLocaleString('id-ID')); }
                    }
                }
            }
        }
    });
})();
</script>
@endpush
