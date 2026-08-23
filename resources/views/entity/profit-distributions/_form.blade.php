<p class="text-muted">
    Laba periode: Rp {{ number_format($availability['profit'], 0, ',', '.') }}.
    Sudah dibagi: Rp {{ number_format($availability['distributed'], 0, ',', '.') }}.
    Tersedia: Rp {{ number_format($availability['available'], 0, ',', '.') }}.
    Bukan prive, modal, atau biaya operasional.
</p>
<div class="form-group">
    <label>Dari Kas/Rekening Usaha</label>
    <select class="form-control" name="source_account_id" required>
        <option value="">—</option>
        @foreach($accounts as $account)
            <option value="{{ $account->id }}" @selected((string) old('source_account_id') === (string) $account->id)>{{ $account->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Ke Family</label>
    <select class="form-control" name="family_public_id" required>
        <option value="">—</option>
        @foreach($families as $family)
            <option value="{{ $family->public_id }}" @selected(old('family_public_id') === $family->public_id)>{{ $family->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Ke Kas/Rekening Family</label>
    <select class="form-control" name="destination_account_id" required>
        <option value="">—</option>
        @foreach($families as $family)
            @foreach($family->activeAccounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('destination_account_id') === (string) $account->id)>
                    {{ $family->name }} — {{ $account->name }}
                </option>
            @endforeach
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Jumlah</label>
    <input class="form-control" name="amount" value="{{ old('amount') }}" required>
</div>
<div class="form-group">
    <label>Tanggal pembagian</label>
    <input type="date" class="form-control" name="distribution_date" value="{{ old('distribution_date', now()->toDateString()) }}" required>
</div>
<div class="form-group">
    <label>Periode laba dari</label>
    <input type="date" class="form-control" name="period_start" value="{{ old('period_start', $availability['from']) }}">
</div>
<div class="form-group">
    <label>Periode laba sampai</label>
    <input type="date" class="form-control" name="period_end" value="{{ old('period_end', $availability['to']) }}">
</div>
<div class="form-group">
    <label>Keterangan</label>
    <input class="form-control" name="description" value="{{ old('description') }}">
</div>
