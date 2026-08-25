@php
    $selectedCategory = old('category_id', isset($income) ? $income->category_id : null);
@endphp
<div class="form-group">
    <label for="source">Sumber Pemasukan</label>
    <input id="source" class="form-control" name="source" value="{{ old('source', $income->source ?? '') }}" placeholder="Contoh: Gaji Agustus" required maxlength="255">
</div>
<div class="form-group">
    <label for="category_id">Kategori</label>
    <select id="category_id" name="category_id" class="form-control" required>
        <option value="">Pilih kategori</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((string) $selectedCategory === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @if($categories->isEmpty())
        <p class="help-block">Belum ada kategori. <a href="{{ route('entity.categories.create', $entity) }}">Buat kategori</a> terlebih dahulu, misalnya “Gaji”.</p>
    @endif
</div>
@include('entity.accounts._select', [
    'accounts' => $accounts,
    'selectedAccountId' => $selectedAccountId ?? ($income->finance_account_id ?? null),
    'accountLabel' => 'Masuk ke Rekening',
])
<div class="form-group">
    <label for="amount">Jumlah</label>
    <x-rupiah-input name="amount" :value="old('amount', isset($income) ? $income->amount : '')" placeholder="Rp 15.000.000" required />
</div>
<div class="form-group">
    <label for="income_date">Tanggal</label>
    <input id="income_date" type="date" class="form-control" name="income_date" value="{{ old('income_date', isset($income) ? $income->income_date?->toDateString() : now()->toDateString()) }}" required>
</div>
<div class="form-group">
    <label for="description">Keterangan</label>
    <input id="description" class="form-control" name="description" value="{{ old('description', $income->description ?? '') }}" maxlength="255" placeholder="Opsional">
</div>
