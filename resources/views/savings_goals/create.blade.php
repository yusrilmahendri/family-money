@extends('welcome')

@section('content')
<div class="main" style="margin-top: 60px;">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Goal tabungan baru</strong></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('savings-goals.store') }}">
                @csrf
                <div class="form-group">
                    <label>Nama goal</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>
                <div class="form-group">
                    <label>Target nominal</label>
                    <input type="text" name="target_amount" id="target_amount" class="form-control" value="{{ old('target_amount') }}" required>
                </div>
                <div class="form-group">
                    <label>Deadline (opsional)</label>
                    <input type="date" name="deadline" class="form-control" value="{{ old('deadline') }}">
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('savings-goals.index') }}" class="btn btn-default">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('target_amount').addEventListener('keyup', function() {
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
</script>
@endpush
