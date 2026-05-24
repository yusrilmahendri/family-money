@extends('welcome')

@section('content')
<div class="insight-page" style="padding: 15px;">

    <h3 style="margin-top: 5px; font-size: 20px; font-weight: 700;">
        <i class="fa fa-lightbulb-o text-warning"></i>
        Insight AI
    </h3>
    <p class="text-muted" style="margin: 0 0 15px; font-size: 13px;">
        Ringkasan otomatis, deteksi anomali, dan proyeksi 3 bulan ke depan menggunakan AI.
    </p>

    @unless($ai_ready)
        <div class="alert alert-warning">
            <strong>Fitur AI belum aktif.</strong>
            Provider yang dipilih: <code>{{ $ai_provider_label ?? 'Gemini' }}</code>.
            Tambahkan <code>{{ $ai_env_key ?? 'GEMINI_API_KEY' }}=...</code> di file <code>.env</code> lalu jalankan
            <code>php artisan config:clear</code>. Bagian Anomali &amp; Forecast tetap berjalan
            tanpa AI (pakai perhitungan statistik), tapi ringkasan naratif memerlukan AI.
        </div>
    @endunless

    {{-- Filter periode --}}
    <form method="GET" action="{{ route('insight.index') }}" class="form-inline" style="margin: 0 0 15px;">
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
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" @selected($m == $month)>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom: 10px;">
            <i class="fa fa-filter"></i> Terapkan
        </button>
    </form>

    {{-- Ringkasan AI --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 18px; background: #fff; margin-bottom: 15px;">
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h5 style="font-weight: 700; margin: 0; font-size: 16px;">
                <i class="fa fa-magic text-primary"></i>
                Ringkasan Bulanan Otomatis
            </h5>
            <div>
                <button id="btn-gen-summary" class="btn btn-primary btn-sm" {{ $ai_ready ? '' : 'disabled' }}>
                    <i class="fa fa-refresh"></i> <span class="lbl">Buat Ringkasan</span>
                </button>
                <button id="btn-share-summary" class="btn btn-default btn-sm" style="display:none;">
                    <i class="fa fa-share-alt"></i> Salin
                </button>
            </div>
        </div>
        <div id="summary-body" class="text-muted" style="min-height: 60px; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">
            Klik <em>Buat Ringkasan</em> untuk meminta AI menulis rangkuman keuangan periode {{ $anomali['bulan'] }}.
        </div>
    </div>

    {{-- Anomali --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 18px; background: #fff; margin-bottom: 15px;">
        <h5 style="font-weight: 700; margin: 0 0 12px; font-size: 16px;">
            <i class="fa fa-exclamation-triangle text-danger"></i>
            Deteksi Anomali — {{ $anomali['bulan'] }}
        </h5>

        @if(empty($anomali['anomalies']))
            <div class="alert alert-success" style="margin: 0;">
                <i class="fa fa-check-circle"></i>
                Tidak ada anomali signifikan terdeteksi. Pola keuangan terlihat normal dibanding 6 bulan lalu.
            </div>
        @else
            @foreach($anomali['anomalies'] as $a)
                @php
                    $level = $a['level'] ?? 'info';
                    $alertClass = match($level) {
                        'danger' => 'alert-danger',
                        'warning' => 'alert-warning',
                        'success' => 'alert-success',
                        default => 'alert-info',
                    };
                @endphp
                <div class="alert {{ $alertClass }}" style="margin-bottom: 8px;">
                    <strong>{{ $a['judul'] }}</strong><br>
                    <small>{{ $a['detail'] }}</small>
                </div>
            @endforeach

            <div style="margin-top: 8px;">
                <button id="btn-explain-anomali" class="btn btn-info btn-sm" {{ $ai_ready ? '' : 'disabled' }}>
                    <i class="fa fa-comment"></i> Minta AI jelaskan
                </button>
            </div>
            <div id="anomali-explanation" class="text-muted" style="margin-top: 12px; white-space: pre-wrap; font-size: 13px; line-height: 1.6;"></div>
        @endif

        <small class="text-muted" style="display:block; margin-top: 10px;">
            Rata-rata pemasukan 6 bulan: <strong>Rp {{ number_format($anomali['pemasukan']['avg'], 0, ',', '.') }}</strong>
            &middot; Rata-rata biaya 6 bulan: <strong>Rp {{ number_format($anomali['biaya']['avg'], 0, ',', '.') }}</strong>
        </small>
    </div>

    {{-- Forecast --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 18px; background: #fff; margin-bottom: 15px;">
        <h5 style="font-weight: 700; margin: 0 0 12px; font-size: 16px;">
            <i class="fa fa-line-chart text-success"></i>
            Proyeksi 3 Bulan ke Depan
        </h5>
        <p class="text-muted" style="margin-bottom: 10px; font-size: 12px;">
            Berdasarkan tren linear dari 6 bulan terakhir. Hasil hanya estimasi — bukan jaminan.
        </p>
        <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
            <table class="table table-bordered" style="min-width: 500px; margin: 0;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Periode</th>
                        <th class="text-right">Proyeksi Pemasukan</th>
                        <th class="text-right">Proyeksi Biaya</th>
                        <th class="text-right">Proyeksi Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($forecast['forecast'] as $f)
                        <tr>
                            <td><strong>{{ $f['label'] }}</strong></td>
                            <td class="text-right text-success">Rp {{ number_format($f['pemasukan'], 0, ',', '.') }}</td>
                            <td class="text-right text-danger">Rp {{ number_format($f['biaya'], 0, ',', '.') }}</td>
                            <td class="text-right">
                                <strong class="{{ $f['laba'] < 0 ? 'text-danger' : 'text-primary' }}">
                                    Rp {{ number_format($f['laba'], 0, ',', '.') }}
                                </strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 15px;">
            <canvas id="forecastChart" height="100"></canvas>
        </div>
    </div>

</div>

<form id="csrfHolder" style="display:none;">@csrf</form>

@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    @media (max-width: 767px) {
        .insight-page { padding: 10px !important; }
        .insight-page h3 { font-size: 18px; }
        .insight-page h5 { font-size: 15px !important; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var csrf = document.querySelector('#csrfHolder input[name=_token]').value;
    var year = {{ $year }};
    var month = {{ $month }};

    var btnGen = document.getElementById('btn-gen-summary');
    var btnShare = document.getElementById('btn-share-summary');
    var sumBody = document.getElementById('summary-body');

    if (btnGen) {
        btnGen.addEventListener('click', function () {
            btnGen.disabled = true;
            btnGen.querySelector('.lbl').textContent = 'Memproses...';
            sumBody.textContent = 'AI sedang menyusun ringkasan, mohon tunggu...';
            sumBody.classList.add('text-muted');

            fetch("{{ route('insight.summary') }}?refresh=1", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ year: year, month: month })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnGen.disabled = false;
                btnGen.querySelector('.lbl').textContent = 'Buat Ulang';
                if (data.ok) {
                    sumBody.classList.remove('text-muted');
                    sumBody.textContent = data.summary;
                    btnShare.style.display = 'inline-block';
                } else {
                    sumBody.classList.add('text-danger');
                    sumBody.textContent = 'Gagal: ' + (data.error || 'unknown error');
                }
            })
            .catch(function (err) {
                btnGen.disabled = false;
                btnGen.querySelector('.lbl').textContent = 'Coba Lagi';
                sumBody.classList.add('text-danger');
                sumBody.textContent = 'Gangguan jaringan: ' + err.message;
            });
        });
    }

    if (btnShare) {
        btnShare.addEventListener('click', function () {
            var text = sumBody.textContent || '';
            if (!text) return;
            navigator.clipboard.writeText(text).then(function () {
                btnShare.innerHTML = '<i class="fa fa-check"></i> Tersalin';
                setTimeout(function () {
                    btnShare.innerHTML = '<i class="fa fa-share-alt"></i> Salin';
                }, 1500);
            });
        });
    }

    var btnExplain = document.getElementById('btn-explain-anomali');
    var explainBox = document.getElementById('anomali-explanation');
    if (btnExplain && explainBox) {
        btnExplain.addEventListener('click', function () {
            btnExplain.disabled = true;
            explainBox.textContent = 'AI sedang menganalisis...';
            fetch("{{ route('insight.explain_anomalies') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ year: year, month: month })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnExplain.disabled = false;
                if (data.ok) {
                    explainBox.textContent = data.explanation;
                } else {
                    explainBox.textContent = 'Gagal: ' + (data.error || 'unknown');
                }
            })
            .catch(function (err) {
                btnExplain.disabled = false;
                explainBox.textContent = 'Gangguan jaringan: ' + err.message;
            });
        });
    }

    // Forecast chart
    var ctx = document.getElementById('forecastChart');
    if (ctx) {
        var history = @json($forecast['history']);
        var forecast = @json($forecast['forecast']);
        var labels = history.map(function (h) { return h.label; })
            .concat(forecast.map(function (f) { return f.label + ' *'; }));
        var pem = history.map(function (h) { return h.pemasukan; })
            .concat(forecast.map(function (f) { return f.pemasukan; }));
        var bia = history.map(function (h) { return h.biaya; })
            .concat(forecast.map(function (f) { return f.biaya; }));

        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: pem,
                        borderColor: 'rgba(40,167,69,1)',
                        backgroundColor: 'rgba(40,167,69,0.15)',
                        tension: 0.3,
                        fill: false,
                    },
                    {
                        label: 'Biaya',
                        data: bia,
                        borderColor: 'rgba(217,83,79,1)',
                        backgroundColor: 'rgba(217,83,79,0.15)',
                        tension: 0.3,
                        fill: false,
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
                            label: function (ctx) {
                                var v = ctx.parsed.y || 0;
                                return ctx.dataset.label + ': Rp ' + v.toLocaleString('id-ID');
                            }
                        }
                    },
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function (v) {
                                return 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : v.toLocaleString('id-ID'));
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
@endpush
