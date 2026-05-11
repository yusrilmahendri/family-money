@extends('welcome')

@section('content')
<div class="main" style="margin-top: 40px;">
    <p><a href="{{ route('savings-goals.index') }}">&larr; Kembali</a></p>
    <h3>{{ $goal->title }}</h3>
    <p>
        Terkumpul: <strong>Rp {{ number_format($saved_total, 0, ',', '.') }}</strong>
        dari target Rp {{ number_format((float) $goal->target_amount, 0, ',', '.') }}
        ({{ $progress_pct }}%)
    </p>
    @if($goal->deadline)
        <p class="text-muted">Deadline: {{ $goal->deadline->format('d M Y') }}</p>
    @endif
    @if($goal->notes)
        <p>{{ $goal->notes }}</p>
    @endif

    <div class="progress" style="height: 14px; margin-bottom: 25px;">
        <div class="progress-bar progress-bar-success" style="width: {{ $progress_pct }}%; min-width: 2%;"></div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="panel panel-success">
                <div class="panel-heading">Setor ke goal</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('savings-goals.contributions.store', $goal) }}">
                        @csrf
                        <div class="form-group">
                            <label>Nominal</label>
                            <input type="text" name="amount" id="contrib_amount" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="contributed_on" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <button type="submit" class="btn btn-success">Catat setoran</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">Riwayat setoran</div>
                <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-condensed">
                        <thead>
                            <tr><th>Tanggal</th><th>Jumlah</th></tr>
                        </thead>
                        <tbody>
                            @forelse($goal->contributions as $c)
                                <tr>
                                    <td>{{ $c->contributed_on->format('d M Y') }}</td>
                                    <td>Rp {{ number_format((float) $c->amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">Belum ada setoran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var el = document.getElementById('contrib_amount');
    if (!el) return;
    el.addEventListener('keyup', function() {
        var angka = this.value.replace(/[^,\d]/g, '').toString();
        var split = angka.split(',');
        var sisa = split[0].length % 3;
        var rupiah = split[0].substr(0, sisa);
        var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            var separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        this.value = rupiah ? 'Rp ' + rupiah : '';
    });
})();
</script>
@endpush
