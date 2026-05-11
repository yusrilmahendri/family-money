@extends('welcome')

@section('content')

<div class="main" style="margin-top: 100px;">
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="panel">


        <div class="panel-body">
            <div class="row">
                <div class="col-lg-12">
                    <form action="{{ route('budgets.update', $budget->id) }}" method="POST">

                      @csrf
                      @method("PUT")

                      <div class="form-group @error('amount') has-error @enderror">
                        <label for="amount">Jumlah Saldo Budget</label>
                        <input type="text" 
                          class="form-control"
                          name="amount" 
                          value="{{ old('amount', 'Rp ' . number_format($budget->amount, 0, ',', '.')) }}"
                          placeholder="masukan Jumlah Saldo"
                         autofocus/>
                    </div>

                    <div class="form-group @error('category_id') has-error @enderror">
                        <label for="category_id">Kategori (opsional)</label>
                        <select class="form-control" name="category_id" id="category_id">
                            <option value="">— Semua / tidak spesifik —</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $budget->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group @error('description') has-error @enderror">
                        <label for="description">Keterangan / Catatan</label>
                        <input type="text" 
                          class="form-control"
                          name="description" 
                          value="{{ old('description', $budget->description) }}"
                          placeholder="masukan keterangan/catatan"  autofocus/>
                    </div>


                    <div class="form-group @error('periode') has-error @enderror">
                        <label for="periode">Tanggal Budget</label>
                        <input type="date" 
                          class="form-control"
                          value="{{ old('periode', optional($budget->periode)->format('Y-m-d')) }}"
                          name="periode" 
                          placeholder="masukan tanggal"  autofocus/>
                    </div>

                <button type="submit" class="btn btn-primary">
                        Simpan Data
                </button>

              </form>
            </div>
         </div>
      </div>
   </div>
</div>

@endsection()

@push('scripts')
<script>
    const amountInput = document.getElementById('amount');

    amountInput.addEventListener('keyup', function(e) {
        this.value = formatRupiah(this.value);
    });

    function formatRupiah(angka) {
        angka = angka.replace(/[^,\d]/g, '').toString();

        let split = angka.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah ? 'Rp ' + rupiah : '';
    }
</script>
@endpush