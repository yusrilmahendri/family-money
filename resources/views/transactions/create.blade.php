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
                                    <form action="{{ route('transactions.store') }}" method="POST" enctype="multipart/form-data">
                                        @csrf

                                        <div class="form-group @error('category_id') has-error @enderror">
                                            <label>Kategori / Jenis Usaha <small class="text-muted">(opsional)</small></label>
                                            <select class="form-control" name="category_id" id="trx_category_id">
                                                <option value="">— Tanpa kategori (saldo umum) —</option>
                                                @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                                                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Pilih kategori jika transaksi diambil dari saldo jenis usaha tertentu. Kosongkan untuk pengeluaran pribadi umum (misal: bayar BPJS).</small>
                                            @error('category_id')
                                                <small class="text-danger d-block">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div id="trx-saldo-info" class="alert alert-info" style="display:none;">
                                            <div><strong>Saldo <span id="trx-info-name"></span> tersedia (setelah dikurangi anggaran &amp; transaksi lain):</strong> <span id="trx-info-tersedia" style="font-weight:700;">-</span></div>
                                        </div>

                                        <div class="form-group @error('total') has-error @enderror">
                                            <label>Total Transaksi</label>
                                            <input type="text" class="form-control" name="total" id="total_transaksi"
                                                placeholder="masukan total transaksi (otomatis jika isi detail barang)">
                                            @error('total')
                                                <small class="text-danger d-block">{{ $message }}</small>
                                            @enderror
                                            @error('amount')
                                                <small class="text-danger d-block">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="form-group @error('description') has-error @enderror">
                                            <label>Keterangan / Catatan</label>
                                            <input type="text" class="form-control"
                                                name="description" value="{{ old('description') }}" placeholder="masukan keterangan/catatan" autofocus />
                                        </div>

                                        <div class="form-group @error('date') has-error @enderror">
                                            <label>Tanggal / Waktu Transaksi</label>
                                            <input type="date" class="form-control"
                                                name="date" required />
                                        </div>

                                        <div class="form-group">
                                            <label>Upload Nota (jika ada)</label>
                                            <input type="file" class="form-control" name="nota" />
                                        </div>

                                        <div class="form-group @error('keterangan_detail') has-error @enderror">
                                            <label>Keterangan Detail (opsional)</label>
                                            <input type="text" class="form-control"
                                                name="keterangan_detail" placeholder="masukan keterangan detail" autofocus />
                                        </div>

                                        <hr>


                                        <button type="submit" class="btn btn-primary">
                                            Simpan Data
                                        </button>

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
    // -------------------------
    // FORMAT RUPIAH
    // -------------------------
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

    // -------------------------
    // HITUNG TOTAL
    // -------------------------
    function hitungTotal() {
        let total = 0;
        let adaDetail = false;

        document.querySelectorAll('.item-row').forEach(row => {
            let harga = parseFloat(row.querySelector('.item-harga').value.replace(/[^0-9]/g, '')) || 0;
            let jumlah = parseFloat(row.querySelector('.item-jumlah').value) || 0;

            if (harga > 0 || jumlah > 0) {
                adaDetail = true;
            }

            total += harga; // sesuai permintaan user
        });

        const totalInput = document.getElementById('total_transaksi');

        if (adaDetail) {
            totalInput.value = formatRupiah(total.toString());
            totalInput.readOnly = true;
        } else {
            totalInput.readOnly = false;
        }
    }

    // -------------------------
    // INDEX DINAMIS UNTUK ITEMS
    // -------------------------
    let itemIndex = 1;

    function generateRow() {
        return `
        <div class="row item-row mb-2" style="margin-top:15px;">

            <div class="col-md-3">
                <input type="text" name="items[${itemIndex}][name]" class="form-control item-nama" placeholder="Nama Barang">
            </div>

            <div class="col-md-2">
                <input type="string" name="items[${itemIndex}][quantity]" class="form-control item-jumlah" placeholder="Jumlah" min="1">
            </div>

            <div class="col-md-2">
                <input type="text" name="items[${itemIndex}][price]" class="form-control item-harga" placeholder="Harga" min="0">
            </div>

            <div class="col-md-3">
                <input type="text" name="items[${itemIndex}][note]" class="form-control item-ket" placeholder="Keterangan (opsional)">
            </div>

            <div class="col-md-2 d-flex" style="margin-top:5px;">
                <button type="button" class="btn btn-danger btn-remove ml-1">X</button>
                <button type="button" class="btn btn-success btn-add ml-1">+</button>
            </div>

        </div>`;
    }

    // -------------------------
    // ADD & REMOVE ROW
    // -------------------------
    document.getElementById('detail-container').addEventListener('click', function(e) {

        if (e.target.classList.contains('btn-add')) {
            itemIndex++;
            document.getElementById('detail-container').insertAdjacentHTML('beforeend', generateRow());
            kontrolHapus();
        }

        if (e.target.classList.contains('btn-remove')) {
            if (document.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
                hitungTotal();
            }
            kontrolHapus();
        }
    });

    // Disable tombol hapus jika hanya 1 row
    function kontrolHapus() {
        let rows = document.querySelectorAll('.item-row');
        rows.forEach(row => {
            row.querySelector('.btn-remove').disabled = (rows.length === 1);
        });
    }
    kontrolHapus();

    // -------------------------
    // EVENT INPUT
    // -------------------------
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-harga')) {
            e.target.value = formatRupiah(e.target.value);
            hitungTotal();
        }

        // if (e.target.classList.contains('item-jumlah')) {
        //     hitungTotal();
        // }
    });

    // Format total manual
    const amountInput = document.getElementById('total_transaksi');
    amountInput.addEventListener('keyup', function(e) {
        if (!amountInput.readOnly) {
            this.value = formatRupiah(this.value);
        }
    });

</script>

<script>
    // -------------------------
    // Info saldo per kategori (AJAX) — separate script agar tidak terblokir error script lain
    // -------------------------
    (function() {
        const trxCategorySelect = document.getElementById('trx_category_id');
        const trxSaldoInfo = document.getElementById('trx-saldo-info');
        const trxInfoName = document.getElementById('trx-info-name');
        const trxInfoTersedia = document.getElementById('trx-info-tersedia');
        if (!trxCategorySelect) return;
        const trxCategoryInfoUrl = "{{ url('/api/v1/budgets/category-info') }}";

        function loadTrxCategoryInfo() {
            const catId = trxCategorySelect.value;
            if (!catId) {
                trxSaldoInfo.style.display = 'none';
                return;
            }
            fetch(`${trxCategoryInfoUrl}/${catId}`)
                .then(r => r.json())
                .then(data => {
                    trxInfoName.textContent = data.category.name;
                    trxInfoTersedia.textContent = data.tersedia_formatted;
                    trxInfoTersedia.style.color = data.tersedia < 0 ? '#d9534f' : '#28a745';
                    trxSaldoInfo.style.display = 'block';
                })
                .catch(() => { trxSaldoInfo.style.display = 'none'; });
        }

        trxCategorySelect.addEventListener('change', loadTrxCategoryInfo);
        if (trxCategorySelect.value) {
            loadTrxCategoryInfo();
        }
    })();
</script>
@endpush
