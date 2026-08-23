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
    <label>Tanggal</label>
    <input type="date" class="form-control" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
</div>
<div class="form-group">
    <label>Keterangan</label>
    <input class="form-control" name="description" value="{{ old('description') }}">
</div>
