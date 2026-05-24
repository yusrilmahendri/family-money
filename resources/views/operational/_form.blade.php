@php
    $isEdit = isset($activity);
    $values = [
        'budget_id' => old('budget_id', $isEdit ? $activity->budget_id : null),
        'name' => old('name', $isEdit ? $activity->name : ''),
        'amount' => old('amount', $isEdit ? 'Rp '.number_format((float) $activity->amount, 0, ',', '.') : ''),
        'activity_date' => old('activity_date', $isEdit ? optional($activity->activity_date)->format('Y-m-d') : date('Y-m-d')),
        'description' => old('description', $isEdit ? $activity->description : ''),
    ];
@endphp

<div class="form-group @error('budget_id') has-error @enderror">
    <label for="budget_id">Anggaran (Jenis Usaha + Periode) <span class="text-danger">*</span></label>
    <select class="form-control" name="budget_id" id="budget_id" required>
        <option value="">— Pilih anggaran —</option>
        @foreach($budgets as $b)
            <option value="{{ $b->id }}" @selected($values['budget_id'] == $b->id)>
                [{{ $b->category?->name ?? '—' }}] {{ $b->description ?: 'Anggaran' }}
                ({{ optional($b->periode)->translatedFormat('M Y') }}) — Plafon Rp {{ number_format((float) $b->amount, 0, ',', '.') }}
            </option>
        @endforeach
    </select>
    <small class="text-muted">Biaya akan mengurangi sisa anggaran ini.</small>
    @error('budget_id') <small class="text-danger">{{ $message }}</small> @enderror
    <div id="budget-info" style="margin-top: 8px; display: none;">
        <div class="alert alert-warning" style="padding: 8px 12px; margin: 0; font-size: 12px;">
            <div>Plafon: <strong id="bi-amount">-</strong></div>
            <div>Sudah terpakai: <strong id="bi-terpakai">-</strong></div>
            <div>Sisa anggaran: <strong id="bi-sisa">-</strong></div>
        </div>
    </div>
</div>

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Nama Biaya <span class="text-danger">*</span></label>
    <input type="text" class="form-control" name="name" id="name"
        value="{{ $values['name'] }}"
        placeholder="Misal: Gaji Karyawan, Upah Harian, Pupuk, BBM" required/>
    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="form-group @error('amount') has-error @enderror">
    <label for="amount">Jumlah Biaya <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="amount" name="amount"
        value="{{ $values['amount'] }}" placeholder="Rp 0" required/>
    @error('amount') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="form-group @error('activity_date') has-error @enderror">
    <label for="activity_date">Tanggal <span class="text-danger">*</span></label>
    <input type="date" class="form-control" name="activity_date"
        value="{{ $values['activity_date'] }}" required/>
    @error('activity_date') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Catatan <small class="text-muted">(opsional)</small></label>
    <input type="text" class="form-control" name="description"
        value="{{ $values['description'] }}" placeholder="Catatan tambahan"/>
</div>
