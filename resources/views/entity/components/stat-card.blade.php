@php
    $negative = $negative ?? false;
    $formatted = $value;
    if (is_numeric($value)) {
        $prefix = ((float) $value) < 0 ? '-' : '';
        $formatted = $prefix.'Rp '.number_format(abs((float) $value), 0, ',', '.');
        $negative = $negative || ((float) $value) < 0;
    }
@endphp
<div class="entity-stat">
    <div class="entity-stat-icon tone-{{ $tone }}">
        <i class="fa {{ $icon }}"></i>
    </div>
    <span class="entity-stat-label">{{ $label }}</span>
    <div class="entity-stat-value {{ $negative ? 'is-negative' : '' }}">{{ $formatted }}</div>
    @if(! empty($hint))
        <p class="entity-stat-hint">{{ $hint }}</p>
    @endif
</div>
