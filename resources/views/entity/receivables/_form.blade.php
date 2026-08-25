<div class="form-group">
    <label>Pihak</label>
    <input class="form-control" name="party_name" value="{{ old('party_name', $receivable->party_name ?? '') }}" required>
</div>
<div class="form-group">
    <label>Jumlah piutang</label>
    <x-rupiah-input name="principal_amount" :value="old('principal_amount', isset($receivable) ? $receivable->principal_amount : '')" :disabled="isset($receivable) && $receivable->hasPayments()" required />
    @if(isset($receivable) && $receivable->hasPayments())
        <input type="hidden" name="principal_amount" value="{{ (int) $receivable->principal_amount }}">
        <p class="text-muted">Pokok tidak dapat diubah setelah ada pembayaran.</p>
    @endif
</div>
<div class="form-group">
    <label>Tanggal piutang</label>
    <input type="date" class="form-control" name="receivable_date" value="{{ old('receivable_date', isset($receivable) ? $receivable->receivable_date?->toDateString() : now()->toDateString()) }}" required>
</div>
<div class="form-group">
    <label>Jatuh tempo</label>
    <input type="date" class="form-control" name="due_date" value="{{ old('due_date', isset($receivable) ? $receivable->due_date?->toDateString() : '') }}">
</div>
<div class="form-group">
    <label>Keterangan</label>
    <input class="form-control" name="description" value="{{ old('description', $receivable->description ?? '') }}">
</div>
