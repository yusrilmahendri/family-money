@php
    /** @var \App\Models\FinanceAccount|null $account */
    $account = $account ?? null;
@endphp

<div class="form-group">
    <label>Nama</label>
    <input class="form-control" name="name" value="{{ old('name', $account?->name) }}" required>
</div>
<div class="form-group">
    <label>Tipe</label>
    <select class="form-control" name="type" required>
        @foreach($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $account?->type?->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Bank</label>
    <input class="form-control" name="bank_name" value="{{ old('bank_name', $account?->bank_name) }}">
</div>
<div class="form-group">
    <label>Nomor rekening</label>
    <input class="form-control" name="account_number" value="{{ old('account_number', $account?->account_number) }}">
</div>
<div class="form-group">
    <label>Deskripsi</label>
    <textarea class="form-control" name="description" rows="3">{{ old('description', $account?->description) }}</textarea>
</div>
<div class="form-group">
    <label>Saldo awal</label>
    <x-rupiah-input name="opening_balance" :value="old('opening_balance', $account?->opening_balance ?? 0)" />
    <small class="text-muted">Hanya saldo awal, bukan saldo berjalan.</small>
</div>
@unless($account)
    <div class="checkbox">
        <label>
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
            Jadikan default
        </label>
    </div>
@endunless
