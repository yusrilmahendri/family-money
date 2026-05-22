@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Ubah Anggaran</h4>
            <form action="{{ route('budgets.update', $budget->id) }}" method="POST">

                @csrf
                @method("PUT")

                    {{-- Jenis Usaha (wajib) --}}
                    <div class="form-group @error('category_id') has-error @enderror">
                        <label for="category_id">Jenis Usaha <span class="text-danger">*</span></label>
                        <select class="form-control" name="category_id" id="category_id" required>
                            <option value="">— Pilih jenis usaha —</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $budget->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    {{-- Info saldo per kategori --}}
                    <div id="saldo-info" class="alert alert-info" style="display:none;">
                        <div><strong>Saldo <span id="info-cat-name"></span>:</strong> <span id="info-saldo">-</span></div>
                        <div><strong>Sudah Dianggarkan (kecuali anggaran ini):</strong> <span id="info-anggaran">-</span></div>
                        <div><strong>Transaksi Pribadi:</strong> <span id="info-transaksi">-</span></div>
                        <div><strong>Saldo Tersedia untuk Anggaran:</strong> <span id="info-tersedia" style="font-weight:700;">-</span></div>
                    </div>

                    <div class="form-group @error('amount') has-error @enderror">
                        <label for="amount">Jumlah Anggaran <span class="text-danger">*</span></label>
                        <input type="text"
                          class="form-control"
                          id="amount"
                          name="amount"
                          value="{{ old('amount', 'Rp ' . number_format($budget->amount, 0, ',', '.')) }}"
                          placeholder="Masukan jumlah anggaran"/>
                        @error('amount')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                        <small class="text-muted">
                            Jumlah ini akan otomatis memotong saldo jenis usaha yang dipilih.
                        </small>
                    </div>

                    <div class="form-group @error('description') has-error @enderror">
                        <label for="description">Keterangan / Catatan</label>
                        <input type="text"
                          class="form-control"
                          name="description"
                          value="{{ old('description', $budget->description) }}"
                          placeholder="Masukan keterangan/catatan"/>
                    </div>


                    <div class="form-group @error('periode') has-error @enderror">
                        <label for="periode">Periode Anggaran <span class="text-danger">*</span></label>
                        <input type="date"
                          class="form-control"
                          value="{{ old('periode', optional($budget->periode)->format('Y-m-d')) }}"
                          name="periode"
                          placeholder="Masukan tanggal" required/>
                    </div>

                <button type="submit" class="btn btn-primary">Simpan Data</button>
                <a href="{{ route('budgets.index') }}" class="btn btn-default">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection()

@push('scripts')
<script>
    const amountInput = document.getElementById('amount');
    const categorySelect = document.getElementById('category_id');
    const saldoInfo = document.getElementById('saldo-info');
    const infoCatName = document.getElementById('info-cat-name');
    const infoSaldo = document.getElementById('info-saldo');
    const infoAnggaran = document.getElementById('info-anggaran');
    const infoTransaksi = document.getElementById('info-transaksi');
    const infoTersedia = document.getElementById('info-tersedia');

    const categoryInfoUrl = "{{ url('/api/v1/budgets/category-info') }}";
    const excludeBudgetId = {{ $budget->id }};

    amountInput.addEventListener('keyup', function(e) {
        this.value = formatRupiah(this.value);
    });

    categorySelect.addEventListener('change', loadCategoryInfo);

    function loadCategoryInfo() {
        const catId = categorySelect.value;
        if (!catId) {
            saldoInfo.style.display = 'none';
            return;
        }
        fetch(`${categoryInfoUrl}/${catId}?exclude_budget_id=${excludeBudgetId}`)
            .then(r => r.json())
            .then(data => {
                infoCatName.textContent = data.category.name;
                infoSaldo.textContent = data.saldo_formatted;
                infoAnggaran.textContent = data.anggaran_formatted;
                infoTransaksi.textContent = data.transaksi_formatted;
                infoTersedia.textContent = data.tersedia_formatted;
                infoTersedia.style.color = data.tersedia < 0 ? '#d9534f' : '#28a745';
                saldoInfo.style.display = 'block';
            })
            .catch(() => {
                saldoInfo.style.display = 'none';
            });
    }

    if (categorySelect.value) {
        loadCategoryInfo();
    }

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
