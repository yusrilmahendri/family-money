@php
    $tone = $tone ?? 'muted';
@endphp
<span class="entity-badge entity-badge-{{ $tone }}">{{ $label }}</span>
