@extends('welcome')

@section('content')
<div class="main" style="margin-top: 40px;">
    <p><a href="{{ route('debts.index') }}">&larr; Kembali</a></p>
    <h3>{{ $debt->title }}</h3>
    <p class="text-muted">
        Pokok: Rp {{ number_format((float) $debt->principal_total, 0, ',', '.') }}
        &middot; Sisa: <strong>Rp {{ number_format((float) $debt->remaining_balance, 0, ',', '.') }}</strong>
        &middot; Cicilan rencana: Rp {{ number_format((float) $debt->monthly_installment, 0, ',', '.') }}
    </p>
    @if($debt->notes)
        <p>{{ $debt->notes }}</p>
    @endif

    <div class="row">
        <div class="col-md-5">
            <div class="panel panel-primary">
                <div class="panel-heading">Catat pembayaran cicilan</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('debts.payments.store', $debt) }}">
                        @csrf
                        <div class="form-group">
                            <label>Jumlah dibayar</label>
                            <input type="text" name="amount" id="pay_amount" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Tanggal bayar</label>
                            <input type="date" name="paid_on" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <input type="text" name="notes" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan pembayaran</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">Riwayat pembayaran</div>
                <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-condensed">
                        <thead>
                            <tr><th>Tanggal</th><th>Jumlah</th><th>Catatan</th></tr>
                        </thead>
                        <tbody>
                            @forelse($debt->payments as $p)
                                <tr>
                                    <td>{{ $p->paid_on->format('d M Y') }}</td>
                                    <td>Rp {{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                                    <td>{{ $p->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">Belum ada pembayaran.</td></tr>
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
    var el = document.getElementById('pay_amount');
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
