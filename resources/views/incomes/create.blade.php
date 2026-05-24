@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Tambah Pemasukan Usaha</h4>
            <p class="text-muted">Catat pemasukan dari hasil usaha Anda, misal: <em>Panen Sawit, Omset Warung Harian, Hasil Ternak</em>.</p>

            <form action="{{ route('incomes.store') }}" method="POST">
                @csrf

                <div class="form-group @error('category_id') has-error @enderror">
                    <label for="category_id">Jenis Usaha <span class="text-danger">*</span></label>
                    <select class="form-control" name="category_id" id="category_id" required>
                        <option value="">— Pilih jenis usaha —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Saldo jenis usaha ini akan otomatis bertambah sebesar pemasukan.</small>
                    @error('category_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('source') has-error @enderror">
                    <label for="source">Sumber Pemasukan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="source" id="source"
                           value="{{ old('source') }}"
                           placeholder="Misal: Panen Sawit Blok A, Omset Warung Senin, dll." required/>
                    @error('source')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('amount') has-error @enderror">
                    <label for="amount">Jumlah Pemasukan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="amount" name="amount"
                           value="{{ old('amount') }}" placeholder="Rp 0" required/>
                    @error('amount')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('income_date') has-error @enderror">
                    <label for="income_date">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="income_date"
                           value="{{ old('income_date', date('Y-m-d')) }}" required/>
                    @error('income_date')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('description') has-error @enderror">
                    <label for="description">Keterangan <small class="text-muted">(opsional)</small></label>
                    <input type="text" class="form-control" name="description"
                           value="{{ old('description') }}" placeholder="Catatan tambahan"/>
                </div>

                <button type="submit" class="btn btn-success">Simpan Pemasukan</button>
                <a href="{{ route('incomes.index') }}" class="btn btn-default">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('amount').addEventListener('keyup', function(e) {
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
