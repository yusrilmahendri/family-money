@extends('entity.layout')

@section('wide')
@endsection

@section('content')
    @php
        $money = fn ($amount) => ((float) $amount < 0 ? '-' : '').'Rp '.number_format(abs((float) $amount), 0, ',', '.');
        $monthIncome = (float) ($monthCashflow['income'] ?? 0);
        $monthExpense = (float) ($monthCashflow['expense'] ?? 0);
        $monthNet = (float) ($monthCashflow['net'] ?? ($monthIncome - $monthExpense));
        $hasCashflow = $monthIncome > 0 || $monthExpense > 0;
        $hasComposition = count($expenseComposition ?? []) > 0;
        $hasActivity = count($recentActivity ?? []) > 0;
    @endphp

    <p class="entity-access-ok"><i class="fa fa-check-circle"></i> Akses private berhasil</p>

    <div class="entity-dash-head">
        <div>
            <h1 class="entity-dash-title">{{ $entity->name }}</h1>
            @include('entity.components.status-badge', [
                'label' => $entity->type->value,
                'tone' => $entity->isFamily() ? 'family' : 'business',
            ])
            @if($entity->is_active)
                @include('entity.components.status-badge', ['label' => 'Aktif', 'tone' => 'success'])
            @endif
            <p class="entity-dash-sub">
                {{ $entity->isFamily() ? 'Ringkasan keuangan keluarga' : 'Ringkasan keuangan usaha' }}
                per {{ $periodLabel }}
            </p>
        </div>
        <div class="entity-dash-art" aria-hidden="true">
            <i class="fa {{ $entity->isFamily() ? 'fa-users' : 'fa-briefcase' }}"></i>
        </div>
    </div>

    <div class="row entity-stat-grid">
        <div class="col-lg-3 col-sm-6 col-xs-12">
            @include('entity.components.stat-card', [
                'icon' => 'fa-money',
                'tone' => 'blue',
                'label' => 'Total Saldo',
                'value' => $totalSaldo,
                'hint' => 'Saldo keseluruhan saat ini',
            ])
        </div>

        @if($entity->isFamily())
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-arrow-down', 'tone' => 'green', 'label' => 'Pemasukan', 'value' => $metrics['pemasukan'], 'hint' => 'Pemasukan keluarga, bukan transfer/prive/laba'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-arrow-up', 'tone' => 'red', 'label' => 'Pengeluaran', 'value' => $metrics['pengeluaran'], 'hint' => 'Total pengeluaran'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-calendar', 'tone' => 'purple', 'label' => 'Pengeluaran Bulan Ini', 'value' => $metrics['pengeluaran_bulan_ini'], 'hint' => 'Total pengeluaran bulan ini'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-list', 'tone' => 'yellow', 'label' => 'Transaksi / Hutang / Goal', 'value' => $metrics['jumlah_transaksi'].' / '.$metrics['jumlah_utang'].' / '.$metrics['jumlah_goal'], 'hint' => 'Jumlah catatan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-briefcase', 'tone' => 'teal', 'label' => 'Modal ke Usaha', 'value' => $metrics['modal_ke_usaha'], 'hint' => 'Penempatan modal'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-user', 'tone' => 'teal', 'label' => 'Prive Diterima', 'value' => $metrics['penerimaan_prive'], 'hint' => 'Penerimaan dari Prive Usaha'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-line-chart', 'tone' => 'pink', 'label' => 'Laba Diterima', 'value' => $metrics['penerimaan_laba'], 'hint' => 'Profit Distribution Received'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-file-text-o', 'tone' => 'blue', 'label' => 'Total Piutang Outstanding', 'value' => $metrics['piutang_outstanding'], 'hint' => 'Sisa piutang'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-clock-o', 'tone' => 'orange', 'label' => 'Piutang Jatuh Tempo', 'value' => $metrics['piutang_jatuh_tempo'], 'hint' => 'Piutang overdue'])
            </div>
        @else
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-arrow-down', 'tone' => 'green', 'label' => 'Pemasukan', 'value' => $metrics['total_pemasukan'], 'hint' => 'Total revenue'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-arrow-up', 'tone' => 'red', 'label' => 'Biaya operasional', 'value' => $metrics['total_biaya_operasional'], 'hint' => 'Biaya aktual'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-balance-scale', 'tone' => $metrics['laba'] < 0 ? 'red' : 'green', 'label' => 'Laba / Rugi', 'value' => $metrics['laba'], 'hint' => $metrics['laba'] < 0 ? 'Rugi' : 'Laba usaha'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-calendar', 'tone' => 'green', 'label' => 'Pemasukan bulan ini', 'value' => $metrics['pemasukan_bulan_ini'], 'hint' => 'Revenue periode berjalan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-calendar', 'tone' => 'red', 'label' => 'Biaya bulan ini', 'value' => $metrics['biaya_bulan_ini'], 'hint' => 'Opex periode berjalan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-line-chart', 'tone' => $metrics['laba_bulan_ini'] < 0 ? 'red' : 'pink', 'label' => 'Laba bulan ini', 'value' => $metrics['laba_bulan_ini'], 'hint' => 'Laba periode berjalan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-calculator', 'tone' => 'purple', 'label' => 'Anggaran (planned)', 'value' => $metrics['anggaran_planned'], 'hint' => 'Anggaran planned'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-check-square-o', 'tone' => 'purple', 'label' => 'Realisasi anggaran', 'value' => $metrics['anggaran_realized'], 'hint' => 'Realisasi aktual'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-hourglass-half', 'tone' => 'purple', 'label' => 'Sisa anggaran', 'value' => $metrics['anggaran_remaining'], 'hint' => 'Planned dikurangi realisasi'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-briefcase', 'tone' => 'teal', 'label' => 'Total Modal Masuk', 'value' => $metrics['total_modal'], 'hint' => 'Modal diterima'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-user', 'tone' => 'teal', 'label' => 'Prive / Owner Withdrawal', 'value' => $metrics['prive'], 'hint' => 'Prive keluar'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-share', 'tone' => 'pink', 'label' => 'Distributed Profit', 'value' => $metrics['distributed_profit'], 'hint' => 'Laba dibagikan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-pie-chart', 'tone' => 'pink', 'label' => 'Undistributed Profit', 'value' => $metrics['undistributed_profit'], 'hint' => 'Laba belum dibagikan'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-file-text-o', 'tone' => 'blue', 'label' => 'Total Piutang Outstanding', 'value' => $metrics['piutang_outstanding'], 'hint' => 'Sisa piutang'])
            </div>
            <div class="col-lg-3 col-sm-6 col-xs-12">
                @include('entity.components.stat-card', ['icon' => 'fa-clock-o', 'tone' => 'orange', 'label' => 'Piutang Jatuh Tempo', 'value' => $metrics['piutang_jatuh_tempo'], 'hint' => 'Piutang overdue'])
            </div>
        @endif
    </div>

    <div class="entity-insight-preview">
        <div>
            <h3>Insight Keuangan</h3>
            <div class="entity-insight-preview-metrics">
                <div>
                    <span>Cash Flow Bulan Ini</span>
                    <strong class="{{ ($insightPreview['cash_flow'] ?? 0) < 0 ? 'is-negative' : '' }}">{{ $money($insightPreview['cash_flow'] ?? 0) }}</strong>
                </div>
                <div>
                    <span>Anomali</span>
                    <strong>{{ (int) ($insightPreview['anomaly_count'] ?? 0) }}</strong>
                </div>
                <div>
                    <span>Critical</span>
                    <strong class="{{ ($insightPreview['critical_count'] ?? 0) > 0 ? 'is-negative' : '' }}">{{ (int) ($insightPreview['critical_count'] ?? 0) }}</strong>
                </div>
            </div>
        </div>
        <a href="{{ route('entity.insight.index', $entity) }}" class="btn btn-primary">Tinjau Insight</a>
    </div>

    <div class="row entity-panel-grid">
        <div class="col-md-4 col-xs-12">
            <div class="entity-panel">
                <h3>Arus Kas Bulan Ini</h3>
                @if($hasCashflow)
                    <div class="entity-chart-wrap">
                        <canvas id="entityCashflowChart"></canvas>
                    </div>
                    <div class="entity-flow-legend">
                        <div class="entity-flow-chip entity-flow-in">
                            <span>Total Pemasukan</span>
                            <strong>{{ $money($monthIncome) }}</strong>
                        </div>
                        <div class="entity-flow-chip entity-flow-out">
                            <span>Total Pengeluaran</span>
                            <strong>{{ $money($monthExpense) }}</strong>
                        </div>
                        <div class="entity-flow-chip entity-flow-net">
                            <span>Cash Flow Bersih</span>
                            <strong class="{{ $monthNet < 0 ? 'text-danger' : '' }}">{{ $money($monthNet) }}</strong>
                        </div>
                    </div>
                @else
                    @include('entity.components.empty-state', ['message' => 'Belum ada data', 'icon' => 'fa-line-chart'])
                @endif
            </div>
        </div>
        <div class="col-md-4 col-xs-12">
            <div class="entity-panel">
                <h3>Komposisi Pengeluaran</h3>
                @if($hasComposition)
                    <div class="entity-chart-wrap">
                        <canvas id="entityExpenseChart"></canvas>
                    </div>
                    <p class="entity-stat-hint" style="margin-top:12px;"><i class="fa fa-lightbulb-o"></i> Berdasarkan kategori entity ini pada {{ $periodLabel }}.</p>
                @else
                    @include('entity.components.empty-state', ['message' => 'Belum ada data', 'icon' => 'fa-pie-chart'])
                @endif
            </div>
        </div>
        <div class="col-md-4 col-xs-12">
            <div class="entity-panel">
                <h3>Aktivitas Terakhir</h3>
                @if($hasActivity)
                    <ul class="entity-activity">
                        @foreach($recentActivity as $row)
                            @php
                                $tone = match ($row['direction'] ?? 'internal') {
                                    'in' => 'green',
                                    'out' => 'red',
                                    default => 'blue',
                                };
                                $icon = match ($row['type'] ?? '') {
                                    'Pemasukan', 'Pembayaran piutang', 'Modal diterima', 'Prive diterima', 'Laba diterima' => 'fa-arrow-down',
                                    'Pengeluaran', 'Biaya operasional' => 'fa-arrow-up',
                                    'Transfer' => 'fa-exchange',
                                    default => 'fa-circle',
                                };
                                $sign = ($row['direction'] ?? '') === 'in' ? '+' : (($row['direction'] ?? '') === 'out' ? '-' : '');
                            @endphp
                            <li>
                                <span class="entity-activity-icon tone-{{ $tone }}"><i class="fa {{ $icon }}"></i></span>
                                <div class="entity-activity-body">
                                    <div class="entity-activity-type">{{ $row['type'] }}</div>
                                    <div class="entity-activity-desc">{{ $row['description'] ?: '—' }}</div>
                                    <div class="entity-activity-meta">{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M Y') : '—' }}</div>
                                </div>
                                <div class="entity-activity-amt {{ $row['direction'] ?? 'internal' }}">
                                    {{ $sign }}{{ $money($row['amount']) }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    @include('entity.components.empty-state', ['message' => 'Belum ada data', 'icon' => 'fa-clock-o'])
                @endif
            </div>
        </div>
    </div>

    <div class="entity-panel entity-annual-panel">
        <div class="entity-annual-head">
            <h3>Tren Keuangan Bulanan — {{ $chartYear }}</h3>
            <form method="get" action="{{ route('entity.dashboard', $entity) }}" class="entity-year-filter">
                <label for="chart_year">Tahun</label>
                <select id="chart_year" name="chart_year" onchange="this.form.submit()">
                    @foreach($chartYears as $yearOption)
                        <option value="{{ $yearOption }}" @selected((int) $yearOption === (int) $chartYear)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="entity-annual-chart">
            <canvas id="entityAnnualChart"></canvas>
        </div>
        <div class="entity-flow-legend">
            <div class="entity-flow-chip entity-flow-in">
                <span>Total Pemasukan Tahun Ini</span>
                <strong>{{ $money($annualCashFlow['income']) }}</strong>
            </div>
            <div class="entity-flow-chip entity-flow-out">
                <span>Total Pengeluaran Tahun Ini</span>
                <strong>{{ $money($annualCashFlow['expense']) }}</strong>
            </div>
            <div class="entity-flow-chip entity-flow-net">
                <span>Cash Flow Bersih Tahun Ini</span>
                <strong class="{{ $annualCashFlow['net'] < 0 ? 'text-danger' : '' }}">{{ $money($annualCashFlow['net']) }}</strong>
            </div>
        </div>
    </div>

    <div class="entity-callout">
        <p><i class="fa fa-info-circle"></i> <strong>Cara saldo dihitung</strong></p>
        <p style="margin-top:8px;">
            Saldo dihitung dari Kas &amp; Rekening entity ini berdasarkan saldo awal,
            pemasukan, pengeluaran aktual, transfer, modal, prive,
            pembagian laba, pembayaran piutang, hutang, dan tabungan.
        </p>
        <details>
            <summary>Lihat detail perhitungan</summary>
            <p style="margin-top:10px; font-size:13px;">
                Total Saldo = saldo awal + pemasukan + modal masuk + prive masuk + pembagian laba masuk + pembayaran piutang
                − pengeluaran aktual − modal keluar − prive keluar − pembagian laba keluar.
                Modal, prive, pembagian laba, dan pokok piutang yang belum dibayar bukan kas, revenue, atau laba.
                Anggaran planned tidak mengurangi saldo sebelum ada realisasi.
                @if($entity->isBusiness())
                    Laba = pemasukan − biaya operasional aktual, bukan posisi kas, sisa anggaran, modal, prive, atau pembagian laba.
                    Saldo awal kas tidak masuk laba.
                @endif
                Saldo global legacy dan tabel saldos tidak dipakai di halaman ini.
            </p>
        </details>
    </div>
@endsection

@push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                var series = @json($cashflowSeries ?? []);
                var composition = @json($expenseComposition ?? []);
                var monthly = @json($monthlyCashFlow ?? []);
                var monthNames = @json(array_values($monthNames ?? []));
                var chartYear = @json($chartYear ?? (int) now()->year);
                var money = function (value) {
                    var prefix = value < 0 ? '-' : '';
                    return prefix + 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Math.abs(value)));
                };

                var cashflowEl = document.getElementById('entityCashflowChart');
                if (cashflowEl && series.length) {
                    new Chart(cashflowEl, {
                        type: 'line',
                        data: {
                            labels: series.map(function (row) { return row.label; }),
                            datasets: [
                                {
                                    label: 'Pemasukan',
                                    data: series.map(function (row) { return row.income; }),
                                    borderColor: '#16a34a',
                                    backgroundColor: 'rgba(22,163,74,0.08)',
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 2
                                },
                                {
                                    label: 'Pengeluaran',
                                    data: series.map(function (row) { return row.expense; }),
                                    borderColor: '#dc2626',
                                    backgroundColor: 'rgba(220,38,38,0.08)',
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 6 } },
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }

                var expenseEl = document.getElementById('entityExpenseChart');
                if (expenseEl && composition.length) {
                    new Chart(expenseEl, {
                        type: 'doughnut',
                        data: {
                            labels: composition.map(function (row) { return row.name; }),
                            datasets: [{
                                data: composition.map(function (row) { return row.total; }),
                                backgroundColor: ['#ef4444', '#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#64748b'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) { return ctx.label + ': ' + money(ctx.parsed); }
                                    }
                                }
                            }
                        }
                    });
                }

                var annualEl = document.getElementById('entityAnnualChart');
                if (annualEl && monthly.length) {
                    new Chart(annualEl, {
                        type: 'line',
                        data: {
                            labels: monthNames.map(function (name) { return name.slice(0, 3); }),
                            datasets: [
                                {
                                    label: 'Pemasukan',
                                    data: monthly.map(function (row) { return row.income; }),
                                    borderColor: '#16a34a',
                                    backgroundColor: 'rgba(22,163,74,0.08)',
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 3
                                },
                                {
                                    label: 'Pengeluaran',
                                    data: monthly.map(function (row) { return row.expense; }),
                                    borderColor: '#dc2626',
                                    backgroundColor: 'rgba(220,38,38,0.08)',
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 3
                                },
                                {
                                    label: 'Cash Flow Bersih',
                                    data: monthly.map(function (row) { return row.net; }),
                                    borderColor: '#2563eb',
                                    backgroundColor: 'rgba(37,99,235,0.08)',
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 3
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } },
                                tooltip: {
                                    callbacks: {
                                        title: function (items) {
                                            var index = items[0] ? items[0].dataIndex : 0;
                                            return (monthNames[index] || '') + ' ' + chartYear;
                                        },
                                        label: function (ctx) {
                                            return ctx.dataset.label + ': ' + money(ctx.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            })();
        </script>
@endpush
