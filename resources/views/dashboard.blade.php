@extends('welcome')

@section('content')
<div class="container mt-4" style="margin-top: 25px;">

    {{-- Card Summary --}}
    <div class="row g-3" style="margin-top: 10px;">
        <div class="col-xs-12 col-sm-6 col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Total Dana Masuk</h6>
                    <h3 class="text-primary" style="font-weight:700; word-break:break-all;">
                        Rp {{ number_format($totalSaldo, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Saldo Rp {{ number_format($totalSaldoMasuk ?? 0, 0, ',', '.') }} + Pemasukan Rp {{ number_format($totalPemasukan ?? 0, 0, ',', '.') }}</small>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Total Pengeluaran Pribadi</h6>
                    <h3 class="text-danger" style="font-weight:700; word-break:break-all;">
                        Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Saldo Bebas (Tersisa)</h6>
                    <h3 class="{{ $sisaSaldo < 0 ? 'text-danger' : 'text-success' }}" style="font-weight:700; word-break:break-all;">
                        Rp {{ number_format($sisaSaldo, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Dana − Anggaran − Transaksi</small>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Laba Usaha Bulan Ini</h6>
                    <h3 class="{{ ($labaBulanIni ?? 0) < 0 ? 'text-danger' : 'text-success' }}" style="font-weight:700; word-break:break-all;">
                        Rp {{ number_format($labaBulanIni ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Pemasukan − Biaya operasional</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Ringkasan bulan ini --}}
    <div class="row">
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="panel panel-success"><div class="panel-body text-center">
                <h6 style="margin:0;">Pemasukan Bulan Ini</h6>
                <h4 style="font-weight:700; margin:5px 0 0;">Rp {{ number_format($pemasukanBulanIni ?? 0, 0, ',', '.') }}</h4>
            </div></div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="panel panel-danger"><div class="panel-body text-center">
                <h6 style="margin:0;">Transaksi Pribadi Bulan Ini</h6>
                <h4 style="font-weight:700; margin:5px 0 0;">Rp {{ number_format($pengeluaranBulanIni ?? 0, 0, ',', '.') }}</h4>
            </div></div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="panel panel-warning"><div class="panel-body text-center">
                <h6 style="margin:0;">Biaya Usaha Bulan Ini</h6>
                <h4 style="font-weight:700; margin:5px 0 0;">Rp {{ number_format($biayaUsahaBulanIni ?? 0, 0, ',', '.') }}</h4>
            </div></div>
        </div>
    </div>

    {{-- Cashflow --}}
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Cashflow {{ date('Y') }}</strong></div>
                <div class="panel-body"><canvas id="cashflowChart" height="80"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-xs-12 col-md-6" style="margin-bottom: 15px;">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Laba/Rugi per Jenis Usaha</strong> <small class="text-muted">bulan ini</small>
                    <a href="{{ route('profit-loss.index') }}" class="pull-right" style="font-size:12px;">Lihat lengkap →</a>
                </div>
                <div class="panel-body" style="padding:0;">
                    <table class="table table-condensed" style="margin:0;">
                        <thead><tr><th>Usaha</th><th class="text-right">Laba</th></tr></thead>
                        <tbody>
                            @forelse($labaPerUsaha ?? [] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-right"><strong class="{{ $row['laba'] < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format($row['laba'], 0, ',', '.') }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-md-6" style="margin-bottom: 15px;">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Top 5 Biaya Usaha</strong> <small class="text-muted">bulan ini</small></div>
                <div class="panel-body" style="padding:0;">
                    <table class="table table-condensed" style="margin:0;">
                        <thead><tr><th>Aktivitas</th><th>Usaha</th><th class="text-right">Biaya</th></tr></thead>
                        <tbody>
                            @forelse($topAktivitas ?? [] as $act)
                                <tr>
                                    <td>{{ $act['name'] }}</td>
                                    <td><small>{{ $act['category'] }}</small></td>
                                    <td class="text-right text-danger">Rp {{ number_format($act['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Belum ada aktivitas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(($recurringDue ?? collect())->isNotEmpty())
    <div class="alert alert-warning" style="margin-bottom: 15px;">
        <strong><i class="fa fa-refresh"></i> Transaksi Berulang Jatuh Tempo:</strong>
        <ul style="margin:8px 0 0; padding-left:18px;">
            @foreach($recurringDue as $r)
                <li><strong>{{ $r->name }}</strong> — Rp {{ number_format((float)$r->amount, 0, ',', '.') }} @if($r->next_due)({{ $r->next_due->translatedFormat('d M Y') }})@endif</li>
            @endforeach
        </ul>
        <a href="{{ route('recurring-transactions.index') }}" class="btn btn-warning btn-xs">Kelola →</a>
    </div>
    @endif

    <div style="margin-bottom: 15px;">
        <small class="text-muted">
            Transaksi terakhir: <strong>{{ $lastTrans ? optional($lastTrans->transaction_date)->format('d M Y') : '-' }}</strong>
            &nbsp;|&nbsp; Total: <strong>{{ $jumlahTransaksi }}</strong>
        </small>
    </div>

        {{-- Filters --}}
    <div class="row mb-4" style="margin-top: 50px; margin-bottom: 10px;">
        {{-- Category Filter --}}
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3" style="margin-bottom: 10px;">
            <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px;">
                <div class="card-body" style="padding: 20px;">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fa fa-filter text-white me-2" style="font-size: 18px;"></i>
                        <label for="monthFilter" class="form-label text-white mb-0" style="font-weight: 600; font-size: 14px;">
                            Filter Kategori
                        </label>
                    </div>
                    <select id="categoryFilter" class="form-select" style="border-radius: 10px; border: 2px solid rgba(255,255,255,0.3); padding: 10px 15px; font-size: 14px; background-color: rgba(255,255,255,0.95);">
                        <option value="">🏷️ Semua Kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">📂 {{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Month Filter --}}
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3" style="margin-bottom: 10px;">
            <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 15px;">
                <div class="card-body" style="padding: 20px;">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fa fa-calendar text-white me-2" style="font-size: 18px;"></i>
                        <label for="monthFilter" class="form-label text-white mb-0" style="font-weight: 600; font-size: 14px;">
                            Filter Bulan
                        </label>
                    </div>
                    <select id="monthFilter" class="form-select" style="border-radius: 10px; border: 2px solid rgba(255,255,255,0.3); padding: 10px 15px; font-size: 14px; background-color: rgba(255,255,255,0.95);">
                        <option value="">📅 Semua Bulan</option>
                        <option value="1">Januari</option>
                        <option value="2">Februari</option>
                        <option value="3">Maret</option>
                        <option value="4">April</option>
                        <option value="5">Mei</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">Agustus</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Desember</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Year Filter --}}
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3" style="margin-bottom: 10px;">
            <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 15px;">
                <div class="card-body" style="padding: 20px;">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fa fa-clock text-white me-2" style="font-size: 18px;"></i>
                        <label for="yearFilter" class="form-label text-white mb-0" style="font-weight: 600; font-size: 14px;">
                            Filter Tahun
                        </label>
                    </div>
                    <select id="yearFilter" class="form-select" style="border-radius: 10px; border: 2px solid rgba(255,255,255,0.3); padding: 10px 15px; font-size: 14px; background-color: rgba(255,255,255,0.95);">
                        <option value="">📆 Semua Tahun</option>
                        @php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                echo "<option value='$year'>$year</option>";
                            }
                        @endphp
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Dynamic Card for Category Saldo --}}
    <div id="categorySaldoCard" style="display: none; margin-bottom: 20px; margin-top: 20px;">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-lg" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px;">
                    <div class="card-body" style="padding: 25px;">
                        <h5 class="card-title text-muted">Total Saldo - <span id="categoryName"></span></h5>
                        <h2 class="font-weight-bold text-primary" id="categorySaldoAmount">Rp 0</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dynamic Card for Filtered Saldo --}}
    <div id="filteredSaldoCard" style="display: none; margin-bottom: 20px; margin-top: 20px;">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-lg" style="border: 2px solid #4facfe; box-shadow: 0px 2px 8px rgba(79,172,254,0.3); border-radius: 12px;">
                    <div class="card-body" style="padding: 25px;">
                        <h5 class="card-title text-muted">Total Saldo - <span id="filterPeriod"></span></h5>
                        <h2 class="font-weight-bold text-info" id="filteredSaldoAmount">Rp 0</h2>
                        <p class="mb-0 text-muted" style="font-size: 14px;">
                            <i class="fa fa-info-circle"></i> Berdasarkan saldo yang masuk
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

        {{-- Export Buttons --}}
    <div class="row mb-3" style="margin-top: 20px; margin-right: 20px;">
        <div class="col-12">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                <a href="{{ route('dashboard.export.excel') }}"
                   class="btn btn-success btn-sm"
                   style="min-width: 110px;">
                   <i class="fa fa-file-excel-o"></i> Excel
                </a>
                <a href="{{ route('dashboard.export.pdf') }}"
                   class="btn btn-danger btn-sm"
                   target="_blank"
                   style="min-width: 110px;">
                   <i class="fa fa-file-pdf-o"></i> PDF
                </a>
            </div>
        </div>
    </div>

     <div class="card shadow-sm border-0 mt-4">
        <div class="card-body">
            <h5 class="fw-bold">Grafik Pengeluaran per Bulan</h5>
            <div id="pengeluaranChart" style="height: 400px;"></div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold">Pie Chart Pengeluaran vs Pemasukan</h5>
                    <div id="pieComparisonChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="fw-bold">Grafik Saldo Berdasarkan Kategori</h5>
                    <div id="saldoKategoriChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>



</div>
@endsection

@push('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>

<script>
// Category Filter
document.getElementById('categoryFilter').addEventListener('change', function() {
    const categoryId = this.value;
    const categoryCard = document.getElementById('categorySaldoCard');

    if (categoryId) {
        // Fetch category saldo
        fetch(`/api/v1/saldos/category/${categoryId}`)
            .then(response => response.json())
            .then(data => {
                const categoryName = this.options[this.selectedIndex].text;
                document.getElementById('categoryName').textContent = categoryName;
                document.getElementById('categorySaldoAmount').textContent = formatRupiah(data.total);
                categoryCard.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                categoryCard.style.display = 'none';
            });
    } else {
        categoryCard.style.display = 'none';
    }
});

// Month and Year Filter
function updateFilteredSaldo() {
    const month = document.getElementById('monthFilter').value;
    const year = document.getElementById('yearFilter').value;
    const filteredCard = document.getElementById('filteredSaldoCard');

    if (month || year) {
        const params = new URLSearchParams();
        if (month) params.append('month', month);
        if (year) params.append('year', year);

        fetch(`/api/v1/saldos/filter?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                let periodText = '';
                const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                if (month && year) {
                    periodText = `${monthNames[month]} ${year}`;
                } else if (month) {
                    periodText = monthNames[month];
                } else if (year) {
                    periodText = `Tahun ${year}`;
                }

                document.getElementById('filterPeriod').textContent = periodText;
                document.getElementById('filteredSaldoAmount').textContent = formatRupiah(data.total);
                filteredCard.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                filteredCard.style.display = 'none';
            });
    } else {
        filteredCard.style.display = 'none';
    }
}

document.getElementById('monthFilter').addEventListener('change', updateFilteredSaldo);
document.getElementById('yearFilter').addEventListener('change', updateFilteredSaldo);

// Format Rupiah
function formatRupiah(number) {
    return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Highcharts - Pengeluaran Bulanan
Highcharts.chart('pengeluaranChart', {
    chart: {
        type: 'line'
    },
    title: {
        text: null
    },
    xAxis: {
        categories: [
            @foreach($pengeluaranBulanan as $p)
                "{{ $p['bulan'] }}",
            @endforeach
        ]
    },
    yAxis: {
        title: {
            text: 'Pengeluaran (Rp)'
        },
        labels: {
            formatter: function() {
                return 'Rp ' + this.value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        }
    },
    tooltip: {
        formatter: function() {
            return '<b>' + this.x + '</b><br/>' +
                   'Pengeluaran: Rp ' + this.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    },
    series: [{
        name: 'Pengeluaran Bulanan',
        data: [
            @foreach($pengeluaranBulanan as $p)
                {{ $p['total'] }},
            @endforeach
        ],
        color: '#0d6efd'
    }],
    credits: {
        enabled: false
    }
});

// Highcharts - Pie Chart Pengeluaran vs Pemasukan
const pieComparisonChart = Highcharts.chart('pieComparisonChart', {
    chart: {
        type: 'pie'
    },
    title: {
        text: null
    },
    tooltip: {
        pointFormatter: function() {
            return '<b>Rp ' + this.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '</b><br/>' +
                   'Persentase: <b>' + this.percentage.toFixed(2) + '%</b>';
        }
    },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b><br>Rp {point.y:,.0f}<br>{point.percentage:.1f}%',
                style: {
                    fontSize: '12px'
                },
                formatter: function() {
                    return '<b>' + this.point.name + '</b><br/>' +
                           'Rp ' + this.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '<br/>' +
                           this.percentage.toFixed(1) + '%';
                }
            },
            showInLegend: true
        }
    },
    series: [{
        name: 'Total',
        colorByPoint: true,
        data: [
            @foreach($comparison as $item)
                {
                    name: "{{ $item['name'] }}",
                    y: {{ $item['y'] }}
                },
            @endforeach
        ]
    }],
    credits: {
        enabled: false
    }
});

// Highcharts - Saldo per Kategori (Pie Chart)
const saldoKategoriChart = Highcharts.chart('saldoKategoriChart', {
    chart: {
        type: 'pie'
    },
    title: {
        text: null
    },
    tooltip: {
        pointFormatter: function() {
            return '<b>Rp ' + this.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '</b><br/>' +
                   'Persentase: <b>' + this.percentage.toFixed(2) + '%</b>';
        }
    },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b><br>Rp {point.y:,.0f}<br>{point.percentage:.1f}%',
                style: {
                    fontSize: '12px'
                },
                formatter: function() {
                    return '<b>' + this.point.name + '</b><br/>' +
                           'Rp ' + this.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '<br/>' +
                           this.percentage.toFixed(1) + '%';
                }
            },
            showInLegend: true
        }
    },
    series: [{
        name: 'Total Saldo',
        colorByPoint: true,
        data: [
            @foreach($saldoPerKategori as $item)
                {
                    name: "{{ $item['name'] }}",
                    y: {{ $item['y'] }}
                },
            @endforeach
        ]
    }],
    credits: {
        enabled: false
    }
});
</script>

<script>
    function updatePieCharts() {
    const month = document.getElementById('monthFilter').value;
    const year = document.getElementById('yearFilter').value;
    const category = document.getElementById('categoryFilter').value;

    const params = new URLSearchParams();
    if (month) params.append('month', month);
    if (year) params.append('year', year);
    if (category) params.append('category', category);

    fetch(`/api/dashboard/summary?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            const comparisonData = data.comparison.map(item => ({
                name: item.name,
                y: Number(item.y)
            }));

            const saldoKategoriData = data.saldoPerKategori.map(item => ({
                name: item.name,
                y: Number(item.y)
            }));

            pieComparisonChart.series[0].setData(comparisonData, true);
            saldoKategoriChart.series[0].setData(saldoKategoriData, true);
        })
        .catch(err => console.error(err));
}

document.getElementById('monthFilter')
    .addEventListener('change', updatePieCharts);

document.getElementById('yearFilter')
    .addEventListener('change', updatePieCharts);

document.getElementById('categoryFilter')
    .addEventListener('change', updatePieCharts);
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('cashflowChart');
    if (!ctx) return;
    var data = @json($cashflowBulanan ?? []);
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.map(function(d){ return d.bulan; }),
            datasets: [
                { label: 'Pemasukan', data: data.map(function(d){ return d.pemasukan; }), backgroundColor: 'rgba(40,167,69,0.7)' },
                { label: 'Pengeluaran Pribadi', data: data.map(function(d){ return d.pengeluaran; }), backgroundColor: 'rgba(217,83,79,0.7)' },
                { label: 'Biaya Usaha', data: data.map(function(d){ return d.biaya_usaha; }), backgroundColor: 'rgba(240,173,78,0.7)' },
                { label: 'Laba', type: 'line', data: data.map(function(d){ return d.laba; }), borderColor: 'rgba(0,123,255,1)', borderWidth: 2, fill: false, tension: 0.3 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { tooltip: { callbacks: { label: function(c){ return c.dataset.label+': Rp '+(c.parsed.y||0).toLocaleString('id-ID'); } } } },
            scales: { y: { ticks: { callback: function(v){ return 'Rp '+v.toLocaleString('id-ID'); } } } }
        }
    });
})();
</script>
@endpush
