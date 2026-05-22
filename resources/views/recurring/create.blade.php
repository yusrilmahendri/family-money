@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Tambah Aturan Transaksi Berulang</h4>
            <p class="text-muted">Misal: <em>Bayar BPJS, Listrik, Internet, Sewa</em> — sistem akan otomatis mencatat transaksi sesuai jadwal.</p>

            @include('recurring._form', [
                'action' => route('recurring-transactions.store'),
                'method' => 'POST',
                'recurring' => null,
                'categories' => $categories,
            ])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('recurring._form_scripts')
@endpush
