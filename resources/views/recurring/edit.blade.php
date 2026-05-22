@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Ubah Aturan Berulang</h4>

            @include('recurring._form', [
                'action' => route('recurring-transactions.update', $recurring),
                'method' => 'PUT',
                'recurring' => $recurring,
                'categories' => $categories,
            ])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('recurring._form_scripts')
@endpush
