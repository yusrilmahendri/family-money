@extends('welcome')

@section('content')
<div class="main" style="margin-top: 60px;">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Ubah goal</strong></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('savings-goals.update', $goal) }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label>Nama goal</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $goal->title) }}" required>
                </div>
                <div class="form-group">
                    <label>Target nominal</label>
                    <input type="text" name="target_amount" id="target_amount" class="form-control"
                        value="{{ old('target_amount', 'Rp '.number_format((float) $goal->target_amount, 0, ',', '.')) }}" required>
                </div>
                <div class="form-group">
                    <label>Deadline</label>
                    <input type="date" name="deadline" class="form-control" value="{{ old('deadline', optional($goal->deadline)->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $goal->notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('savings-goals.index') }}" class="btn btn-default">Kembali</a>
            </form>
            <hr>
            <form id="delete-goal-form" method="POST" action="{{ route('savings-goals.destroy', $goal) }}">
                @csrf
                @method('DELETE')
                <button type="button" id="btn-delete-goal" class="btn btn-danger btn-sm">Hapus goal</button>
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

document.getElementById('btn-delete-goal').addEventListener('click', function() {
    swal({
        title: 'Hapus goal ini?',
        text: 'Semua setoran/contribusi akan ikut terhapus.',
        icon: 'warning',
        buttons: {
            cancel: { text: 'Batal', value: null, visible: true },
            confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
        },
        dangerMode: true,
    }).then(function(ok) {
        if (ok) document.getElementById('delete-goal-form').submit();
    });
});
</script>
@endpush
