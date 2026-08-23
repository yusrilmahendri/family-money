<div class="form-group">
    <label>Dari Kas/Rekening Family</label>
    <select class="form-control" name="source_account_id" required>
        <option value="">—</option>
        @foreach($accounts as $account)
            <option value="{{ $account->id }}" @selected((string) old('source_account_id') === (string) $account->id)>{{ $account->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Ke Usaha</label>
    <select class="form-control" name="business_public_id" required>
        <option value="">—</option>
        @foreach($businesses as $business)
            <option value="{{ $business->public_id }}" @selected(old('business_public_id') === $business->public_id)>{{ $business->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Ke Kas/Rekening Usaha</label>
    <select class="form-control" name="destination_account_id" required>
        <option value="">—</option>
        @foreach($businesses as $business)
            @foreach($business->activeAccounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('destination_account_id') === (string) $account->id)>
                    {{ $business->name }} — {{ $account->name }}
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
