@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Ubah Pemasukan</h4>

            <form action="{{ route('incomes.update', $income) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group @error('category_id') has-error @enderror">
                    <label for="category_id">Jenis Usaha <span class="text-danger">*</span></label>
                    <select class="form-control" name="category_id" required>
                        <option value="">— Pilih jenis usaha —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $income->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Saldo jenis usaha ini akan otomatis disesuaikan.</small>
                    @error('category_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('source') has-error @enderror">
                    <label for="source">Sumber Pemasukan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="source"
                           value="{{ old('source', $income->source) }}" required/>
                    @error('source')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('amount') has-error @enderror">
                    <label for="amount">Jumlah Pemasukan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="amount" name="amount"
                           value="{{ old('amount', 'Rp '.number_format((float) $income->amount, 0, ',', '.')) }}" required/>
                    @error('amount')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group @error('income_date') has-error @enderror">
                    <label for="income_date">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="income_date"
                           value="{{ old('income_date', optional($income->income_date)->format('Y-m-d')) }}" required/>
                </div>

                <div class="form-group @error('description') has-error @enderror">
                    <label for="description">Keterangan</label>
                    <input type="text" class="form-control" name="description"
                           value="{{ old('description', $income->description) }}"/>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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
