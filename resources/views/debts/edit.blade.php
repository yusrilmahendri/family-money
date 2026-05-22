@extends('welcome')

@section('content')
<div class="main" style="margin-top: 60px;">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Ubah utang</strong></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('debts.update', $debt) }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label>Judul / kreditur</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $debt->title) }}" required>
                </div>
                <div class="form-group">
                    <label>Total pokok</label>
                    <input type="text" name="principal_total" id="principal_total" class="form-control"
                        value="{{ old('principal_total', 'Rp '.number_format((float) $debt->principal_total, 0, ',', '.')) }}" required>
                </div>
                <div class="form-group">
                    <label>Sisa utang</label>
                    <input type="text" name="remaining_balance" id="remaining_balance" class="form-control"
                        value="{{ old('remaining_balance', 'Rp '.number_format((float) $debt->remaining_balance, 0, ',', '.')) }}" required>
                </div>
                <div class="form-group">
                    <label>Cicilan per bulan</label>
                    <input type="text" name="monthly_installment" id="monthly_installment" class="form-control"
                        value="{{ old('monthly_installment', 'Rp '.number_format((float) $debt->monthly_installment, 0, ',', '.')) }}">
                </div>
                <div class="form-group">
                    <label>Tanggal jatuh tempo tiap bulan (1–31)</label>
                    <input type="number" name="due_day" class="form-control" min="1" max="31" value="{{ old('due_day', $debt->due_day) }}">
                </div>
                <div class="form-group">
                    <label>Mulai pinjaman</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($debt->start_date)->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $debt->notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('debts.index') }}" class="btn btn-default">Kembali</a>
            </form>
            <hr>
            <form id="delete-debt-form" method="POST" action="{{ route('debts.destroy', $debt) }}">
                @csrf
                @method('DELETE')
                <button type="button" id="btn-delete-debt" class="btn btn-danger btn-sm">Hapus utang</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
['principal_total','remaining_balance','monthly_installment'].forEach(function(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('keyup', function() {
        this.value = formatRupiah(this.value);
    });
});
function formatRupiah(angka) {
    angka = angka.replace(/[^,\d]/g, '').toString();
    var split = angka.split(',');
    var sisa = split[0].length % 3;
    var rupiah = split[0].substr(0, sisa);
    var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
        var separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah ? 'Rp ' + rupiah : '';
}

document.getElementById('btn-delete-debt').addEventListener('click', function() {
    swal({
        title: 'Hapus utang ini?',
        text: 'Seluruh riwayat cicilan juga akan ikut terhapus.',
        icon: 'warning',
        buttons: {
            cancel: { text: 'Batal', value: null, visible: true },
            confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
        },
        dangerMode: true,
    }).then(function(ok) {
        if (ok) document.getElementById('delete-debt-form').submit();
    });
});
</script>
@endpush
