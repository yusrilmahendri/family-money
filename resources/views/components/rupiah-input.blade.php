@props([
    'name',
    'id' => null,
    'value' => '',
    'required' => false,
    'disabled' => false,
    'placeholder' => 'Rp 1.000.000',
])
<input
    {{ $attributes->merge(['class' => 'form-control js-rupiah']) }}
    type="text"
    inputmode="numeric"
    autocomplete="off"
    name="{{ $name }}"
    id="{{ $id ?? $name }}"
    value="{{ rupiah_input($value) }}"
    placeholder="{{ $placeholder }}"
    @required($required)
    @disabled($disabled)
>
