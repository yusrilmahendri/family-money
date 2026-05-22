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

                                    <form action="{{ route('transactions.update', $transaction->id) }}"
                                          method="POST" enctype="multipart/form-data">

                                        @csrf
                                        @method('PUT')

                                        <div class="form-group @error('category_id') has-error @enderror">
                                            <label>Kategori / Jenis Usaha <small class="text-muted">(opsional)</small></label>
                                            <select class="form-control" name="category_id">
                                                <option value="">— Tanpa kategori (saldo umum) —</option>
                                                @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                                                    <option value="{{ $cat->id }}" @selected(old('category_id', $transaction->category_id) == $cat->id)>{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('category_id')
                                                <small class="text-danger d-block">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label>Total Transaksi</label>
                                            <input type="text"
                                                class="form-control"
                                                name="amount"
                                                id="total_transaksi"
                                                value="Rp {{ number_format($transaction->amount, 0, ',', '.') }}">
                                            @error('amount')
                                                <small class="text-danger d-block">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label>Keterangan / Catatan</label>
                                            <input type="text"
                                                class="form-control"
                                                name="description"
                                                value="{{ $transaction->description }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Tanggal / Waktu Transaksi</label>
                                            <input type="date"
                                                class="form-control"
                                                name="date"
                                                value="{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('Y-m-d') }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Upload Nota (Opsional)</label>
                                            <input type="file" class="form-control" name="nota">
                                        </div>

                                         <div class="form-group">
                                            <label>Keterangan Detail (opsional)</label>
                                            <input type="text"
                                                class="form-control"
                                                name="keterangan_detail"
                                                value="{{ $transaction->keterangan_detail }}">
                                        </div>

                                        <hr>

                                        <button type="submit" class="btn btn-primary">Update Data</button>

                                    </form>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
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

        return rupiah ? 'Rp ' + rupiah : '';
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('.item-harga').forEach(input => {
            let angka = parseFloat(input.value.replace(/[^0-9]/g, '')) || 0;
            total += angka;
        });
        document.getElementById('total_transaksi').value = formatRupiah(total.toString());
    }

    document.getElementById('detail-container').addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-add')) {
            itemIndex++;
            document.getElementById('detail-container').insertAdjacentHTML('beforeend', generateRow());
        }

        if (e.target.classList.contains('btn-remove')) {
            if (document.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
                hitungTotal();
            }
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-harga')) {
            e.target.value = formatRupiah(e.target.value);
            hitungTotal();
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.item-harga').forEach(input => {
            let angka = input.value.replace(/[^0-9]/g, '');
            input.value = formatRupiah(angka);
        });
        hitungTotal();
    });
</script>
@endpush
