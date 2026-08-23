@php
    /** @var \App\Models\FinanceEntity|null $entity */
    $entity = $entity ?? null;
@endphp

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control"
        value="{{ old('name', $entity?->name) }}" required>
    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
</div>

@php
    $typeLocked = $entity?->hasFinancialRecords() ?? false;
@endphp
<div class="form-group @error('type') has-error @enderror">
    <label for="type">Type <span class="text-danger">*</span></label>
    @if($typeLocked)
        <input type="hidden" name="type" value="{{ $entity->type->value }}">
        <select id="type" class="form-control" disabled>
            @foreach($types as $type)
                <option value="{{ $type->value }}" @selected($entity->type->value === $type->value)>
                    {{ $type->value }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Tipe tidak dapat diubah karena entity sudah memiliki data keuangan.</small>
    @else
        <select name="type" id="type" class="form-control" required>
            @foreach($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $entity?->type?->value) === $type->value)>
                    {{ $type->value }}
                </option>
            @endforeach
        </select>
    @endif
    @error('type') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="form-group @error('slug') has-error @enderror">
    <label for="slug">Slug @if(! $entity)<small class="text-muted">(opsional, otomatis jika kosong)</small>@endif</label>
    <input type="text" name="slug" id="slug" class="form-control"
        value="{{ old('slug', $entity?->slug) }}"
        @if($entity) required @endif>
    @error('slug') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $entity?->description) }}</textarea>
    @error('description') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<div class="checkbox">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $entity?->is_active ?? true))>
        Aktif
    </label>
</div>
