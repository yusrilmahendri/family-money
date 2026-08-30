<div class="form-group">
    <label for="name">Nama anggaran</label>
    <input id="name" name="name" type="text" class="form-control" required value="{{ old('name', $budget->name ?? '') }}">
</div>
<div class="form-group">
    <label for="period_start">Awal periode</label>
    <input id="period_start" name="period_start" type="date" class="form-control" required value="{{ old('period_start', isset($budget) ? $budget->period_start->toDateString() : '') }}">
</div>
<div class="form-group">
    <label for="period_end">Akhir periode</label>
    <input id="period_end" name="period_end" type="date" class="form-control" required value="{{ old('period_end', isset($budget) ? $budget->period_end->toDateString() : '') }}">
</div>
<div class="form-group">
    <label for="allocated_amount">Jumlah alokasi</label>
    <input id="allocated_amount" name="allocated_amount" type="text" class="form-control" required value="{{ old('allocated_amount', isset($budget) ? rupiah($budget->allocated_amount) : '') }}">
</div>
