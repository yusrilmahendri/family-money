@extends('welcome')

@section('content')
<div class="recurring-page" style="padding: 15px;">

    <div class="row row-eq" style="margin-top: 15px;">
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Aturan Aktif</h6>
                <h3 class="text-primary" style="font-weight: 700; margin: 0; font-size: 22px;">{{ $total_aktif }}</h3>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Akan Jatuh Tempo</h6>
                <h3 class="text-warning" style="font-weight: 700; margin: 0; font-size: 22px;">{{ $total_due }}</h3>
                <small class="text-muted">tersisa hari ini / sebelumnya</small>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Auto-posting</h6>
                <p style="margin: 0; font-size: 13px;" class="text-muted">
                    Saat Anda membuka halaman ini, transaksi otomatis dibuatkan untuk aturan yang jatuh tempo.
                </p>
            </div>
        </div>
    </div>

    @if($upcoming->isNotEmpty())
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; margin-bottom: 15px;">
        <h5 style="font-weight: 700; margin-bottom: 10px; font-size: 15px;">Jatuh Tempo Terdekat</h5>
        <ul style="margin: 0; padding-left: 18px;">
            @foreach($upcoming as $up)
                <li>
                    <strong>{{ $up->name }}</strong> — Rp {{ number_format((float) $up->amount, 0, ',', '.') }}
                    <span class="text-muted">({{ $up->category?->name ?? 'umum' }})</span>
                    @if($up->next_due)
                        — <span class="{{ $up->next_due->isPast() ? 'text-danger' : '' }}">
                            {{ $up->next_due->translatedFormat('d M Y') }}
                            @if($up->next_due->isToday()) (hari ini) @endif
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="row" style="margin-bottom: 10px;">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0; font-size: 18px;">Daftar Aturan Berulang</h3>
        </div>
        <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
            <a href="{{ route('recurring-transactions.create') }}" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Tambah Aturan
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="dataTable" style="min-width: 800px;">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Frekuensi</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<form action="" method="post" id="deleteForm">
    @csrf
    @method("DELETE")
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    @media (min-width: 768px) {
        .recurring-page .row.row-eq { display: flex; flex-wrap: wrap; }
        .recurring-page .row.row-eq > [class*='col-'] { display: flex; }
        .recurring-page .row.row-eq > [class*='col-'] > .summary-card { width: 100%; flex: 1; }
    }
    @media (max-width: 767px) {
        .recurring-page { padding: 10px !important; }
    }
</style>

<script>
$(function(){
    $('#dataTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: "{{ route('recurring.data') }}",
        columns: [
            {data: 'name'},
            {data: 'category'},
            {data: 'amount'},
            {data: 'frequency_label'},
            {data: 'next_due'},
            {data: 'status'},
            {data: 'action', orderable: false, searchable: false}
        ]
    });
});
</script>
@endpush
