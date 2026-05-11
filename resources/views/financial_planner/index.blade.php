@extends('welcome')

@section('content')
<div class="container-fluid" style="margin-top: 20px;">

    @if($over_budget)
        <div class="alert alert-danger">
            <strong>Perhatian:</strong> pengeluaran bulan ini melebihi plafon anggaran yang tercatat.
            <a href="{{ route('budgets.index') }}" class="alert-link">Lihat anggaran</a>
        </div>
    @endif

    <div class="row g-3" style="margin-bottom: 20px;">
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-primary" style="border-radius: 8px;">
                <div class="panel-body">
                    <h6 class="text-muted" style="margin-top:0;">Pengeluaran hari ini</h6>
                    <h3 class="text-danger" style="margin:0;">Rp {{ number_format($today_spend, 0, ',', '.') }}</h3>
                    <small class="text-muted">Berdasarkan tanggal transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default" style="border-radius: 8px;">
                <div class="panel-body">
                    <h6 class="text-muted" style="margin-top:0;">Anggaran bulan ini</h6>
                    <h3 style="margin:0;">Rp {{ number_format($budget_cap, 0, ',', '.') }}</h3>
                    <small class="text-muted">Plafon dari data anggaran</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default" style="border-radius: 8px;">
                <div class="panel-body">
                    <h6 class="text-muted" style="margin-top:0;">Terpakai bulan ini</h6>
                    <h3 class="text-warning" style="margin:0;">Rp {{ number_format($spent_month, 0, ',', '.') }}</h3>
                    <small class="text-muted">Total transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-success" style="border-radius: 8px;">
                <div class="panel-body">
                    <h6 class="text-muted" style="margin-top:0;">Sisa anggaran</h6>
                    <h3 class="text-success" style="margin:0;">Rp {{ number_format($budget_cap - $spent_month, 0, ',', '.') }}</h3>
                    <small class="text-muted">Estimasi jika plafon &gt; 0</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Pengeluaran harian (14 hari)</strong></div>
                <div class="panel-body">
                    <div id="plannerDailyChart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Rekap cashflow bulanan (otomatis)</strong></div>
                <div class="panel-body">
                    <p class="text-muted small">Pemasukan dari saldo, pengeluaran dari transaksi, cicilan dari pembayaran utang.</p>
                    <div id="plannerCashflowChart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Tabungan ke goals per bulan</strong></div>
                <div class="panel-body">
                    <div id="plannerGoalsChart" style="height: 260px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <strong class="pull-left" style="padding-top: 8px;">Ringkas utang</strong>
                    <a href="{{ route('debts.index') }}" class="btn btn-primary btn-sm pull-right">Kelola utang</a>
                </div>
                <div class="panel-body" style="max-height: 280px; overflow-y: auto;">
                    @forelse($debts as $d)
                        <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                            <a href="{{ route('debts.show', $d) }}"><strong>{{ $d->title }}</strong></a>
                            <div class="small text-muted">
                                Sisa: Rp {{ number_format((float) $d->remaining_balance, 0, ',', '.') }}
                                &middot; Cicilan: Rp {{ number_format((float) $d->monthly_installment, 0, ',', '.') }}
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">Belum ada utang / cicilan. <a href="{{ route('debts.create') }}">Tambah</a></p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <strong class="pull-left" style="padding-top: 8px;">Goals tabungan</strong>
                    <a href="{{ route('savings-goals.index') }}" class="btn btn-primary btn-sm pull-right">Kelola goals</a>
                </div>
                <div class="panel-body" style="max-height: 280px; overflow-y: auto;">
                    @forelse($goals as $g)
                        @php
                            $saved = $g->savedTotal();
                            $target = (float) $g->target_amount;
                            $pct = $target > 0 ? min(100, round(($saved / $target) * 100)) : 0;
                        @endphp
                        <div style="margin-bottom: 12px;">
                            <a href="{{ route('savings-goals.show', $g) }}"><strong>{{ $g->title }}</strong></a>
                            <div class="progress" style="height: 8px; margin-top: 4px;">
                                <div class="progress-bar progress-bar-success" style="width: {{ $pct }}%;"></div>
                            </div>
                            <div class="small text-muted">
                                Rp {{ number_format($saved, 0, ',', '.') }} / Rp {{ number_format($target, 0, ',', '.') }} ({{ $pct }}%)
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">Belum ada goal. <a href="{{ route('savings-goals.create') }}">Tambah goal</a></p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 10px; margin-bottom: 30px;">
        <div class="col-md-12">
            <a href="{{ route('transactions.create') }}" class="btn btn-default"><em class="fa fa-plus"></em> Transaksi</a>
            <a href="{{ route('saldos.create') }}" class="btn btn-default"><em class="fa fa-money"></em> Saldo masuk</a>
            <a href="{{ route('budgets.create') }}" class="btn btn-default"><em class="fa fa-sliders"></em> Anggaran</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
(function() {
    const dailyLabels = @json($daily_labels);
    const dailyValues = @json($daily_values);
    const cashflow = @json($cashflow);
    const goalFlow = @json($goal_flow);

    function fmt(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID');
    }

    Highcharts.chart('plannerDailyChart', {
        chart: { type: 'column' },
        title: { text: null },
        xAxis: { categories: dailyLabels },
        yAxis: { title: { text: 'Rp' }, labels: { formatter: function() { return fmt(this.value); } } },
        tooltip: { formatter: function() { return '<b>' + this.x + '</b><br/>' + fmt(this.y); } },
        series: [{ name: 'Pengeluaran', data: dailyValues, color: '#d9534f' }],
        credits: { enabled: false }
    });

    const cfLabels = cashflow.map(function(r) { return r.label; });
    Highcharts.chart('plannerCashflowChart', {
        chart: { type: 'column' },
        title: { text: null },
        xAxis: { categories: cfLabels, labels: { rotation: -45 } },
        yAxis: { title: { text: 'Rp' }, labels: { formatter: function() { return fmt(this.value); } } },
        tooltip: { shared: true, formatter: function() {
            let s = '<b>' + this.x + '</b><br/>';
            this.points.forEach(function(p) { s += p.series.name + ': ' + fmt(p.y) + '<br/>'; });
            return s;
        } },
        series: [
            { name: 'Pemasukan (saldo)', data: cashflow.map(function(r) { return r.inflow; }), color: '#5cb85c' },
            { name: 'Pengeluaran', data: cashflow.map(function(r) { return r.expenses; }), color: '#d9534f' },
            { name: 'Cicilan utang', data: cashflow.map(function(r) { return r.debt_payments; }), color: '#f0ad4e' },
            { name: 'Net', type: 'line', data: cashflow.map(function(r) { return r.net; }), color: '#337ab7' }
        ],
        credits: { enabled: false }
    });

    Highcharts.chart('plannerGoalsChart', {
        chart: { type: 'column' },
        title: { text: null },
        xAxis: { categories: goalFlow.map(function(r) { return r.label; }), labels: { rotation: -45 } },
        yAxis: { title: { text: 'Rp' }, labels: { formatter: function() { return fmt(this.value); } } },
        tooltip: { formatter: function() { return '<b>' + this.x + '</b><br/>' + fmt(this.y); } },
        series: [{ name: 'Masuk ke goals', data: goalFlow.map(function(r) { return r.saved_to_goals; }), color: '#5bc0de' }],
        credits: { enabled: false }
    });
})();
</script>
@endpush
