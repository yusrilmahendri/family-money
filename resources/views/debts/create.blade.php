@extends('welcome')

@section('content')
<div class="main" style="margin-top: 60px;">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Catat utang / cicilan</strong></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('debts.store') }}">
                @csrf
                <div class="form-group">
                    <label>Judul / kreditur</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>
                <div class="form-group">
                    <label>Total pokok pinjaman</label>
                    <input type="text" name="principal_total" id="principal_total" class="form-control" value="{{ old('principal_total') }}" required>
                </div>
                <div class="form-group">
                    <label>Sisa utang sekarang (kosongkan = sama dengan pokok)</label>
                    <input type="text" name="remaining_balance" id="remaining_balance" class="form-control" value="{{ old('remaining_balance') }}">
                </div>
                <div class="form-group">
                    <label>Cicilan per bulan (opsional)</label>
                    <input type="text" name="monthly_installment" id="monthly_installment" class="form-control" value="{{ old('monthly_installment') }}">
                </div>
                <div class="form-group">
                    <label>Tanggal jatuh tempo tiap bulan (1–31, opsional)</label>
                    <input type="number" name="due_day" class="form-control" min="1" max="31" value="{{ old('due_day') }}">
                </div>
                <div class="form-group">
                    <label>Mulai pinjaman (opsional)</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}">
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('debts.index') }}" class="btn btn-default">Batal</a>
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
</script>
@endpush
