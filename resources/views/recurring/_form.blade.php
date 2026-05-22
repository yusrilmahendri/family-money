<form action="{{ $action }}" method="POST">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="form-group @error('name') has-error @enderror">
        <label>Nama Transaksi <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="name"
               value="{{ old('name', $recurring?->name) }}"
               placeholder="Misal: Bayar BPJS, Listrik, Internet" required/>
        @error('name')<small class="text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group @error('amount') has-error @enderror">
        <label>Jumlah <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="amount" name="amount"
               value="{{ old('amount', $recurring ? 'Rp '.number_format((float) $recurring->amount, 0, ',', '.') : '') }}"
               placeholder="Rp 0" required/>
        @error('amount')<small class="text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="form-group @error('category_id') has-error @enderror">
        <label>Kategori <small class="text-muted">(opsional)</small></label>
        <select class="form-control" name="category_id">
            <option value="">— Tanpa kategori —</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $recurring?->category_id) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group @error('frequency') has-error @enderror">
        <label>Frekuensi <span class="text-danger">*</span></label>
        <select class="form-control" name="frequency" id="frequency" required>
            @foreach(['daily' => 'Setiap Hari', 'weekly' => 'Setiap Minggu', 'monthly' => 'Setiap Bulan', 'yearly' => 'Setiap Tahun'] as $val => $label)
                <option value="{{ $val }}" @selected(old('frequency', $recurring?->frequency ?? 'monthly') == $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group" id="day-of-week-group" style="display:none;">
        <label>Hari dalam Minggu</label>
        <select class="form-control" name="day_of_week">
            @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $day)
                <option value="{{ $i }}" @selected(old('day_of_week', $recurring?->day_of_week) == $i)>{{ $day }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group" id="day-of-month-group">
        <label>Tanggal dalam Bulan (1–31)</label>
        <input type="number" class="form-control" name="day_of_month" min="1" max="31"
               value="{{ old('day_of_month', $recurring?->day_of_month ?? 1) }}"/>
        <small class="text-muted">Untuk frekuensi bulanan/tahunan.</small>
    </div>

    <div class="form-group @error('start_date') has-error @enderror">
        <label>Mulai Berlaku <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="start_date"
               value="{{ old('start_date', optional($recurring?->start_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required/>
    </div>

    <div class="form-group @error('end_date') has-error @enderror">
        <label>Berakhir <small class="text-muted">(kosongkan = tidak berakhir)</small></label>
        <input type="date" class="form-control" name="end_date"
               value="{{ old('end_date', optional($recurring?->end_date)->format('Y-m-d')) }}"/>
    </div>

    <div class="form-group">
        <label>Catatan <small class="text-muted">(opsional)</small></label>
        <input type="text" class="form-control" name="description"
               value="{{ old('description', $recurring?->description) }}"/>
    </div>

    <div class="checkbox">
        <label>
            <input type="checkbox" name="active" value="1"
                   @checked(old('active', $recurring?->active ?? true))>
            Aktif (otomatis posting sesuai jadwal)
        </label>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('recurring-transactions.index') }}" class="btn btn-default">Batal</a>
</form>
