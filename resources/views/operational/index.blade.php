@extends('welcome')

@section('content')
<div class="op-page" style="padding: 15px;">

    {{-- Ringkasan --}}
    <div class="row row-eq" style="margin-bottom: 5px;">
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Biaya Bulan Ini</h6>
                <h3 class="text-danger" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_bulan_ini ?? 0, 0, ',', '.') }}
                </h3>
                <small class="text-muted">{{ now()->translatedFormat('F Y') }}</small>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Biaya Tahun Ini</h6>
                <h3 class="text-warning" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_tahun_ini ?? 0, 0, ',', '.') }}
                </h3>
                <small class="text-muted">{{ now()->year }}</small>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Semua Biaya</h6>
                <h3 class="text-primary" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_semua ?? 0, 0, ',', '.') }}
                </h3>
                <small class="text-muted">Akumulasi</small>
            </div>
        </div>
    </div>

    <div class="alert alert-info" style="font-size: 13px;">
        <strong>Apa itu Biaya Operasional?</strong>
        Adalah biaya yang dikeluarkan dari sebuah <strong>Anggaran</strong> — misalnya
        <em>gaji karyawan, upah harian, pupuk, BBM, bahan baku</em>, dsb.
        Setiap biaya yang Anda catat di sini akan otomatis mengurangi sisa anggaran terkait
        dan akan muncul sebagai <strong>"Biaya"</strong> di laporan Laba / Rugi.
    </div>

    {{-- Header daftar --}}
    <div class="row" style="margin-bottom: 10px;">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0; font-size: 18px;">Daftar Biaya Operasional</h3>
        </div>
        <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
            @if($budgets->count() === 0)
                <span class="text-muted" style="display:inline-block; margin-right:10px;">
                    Buat <a href="{{ route('budgets.create') }}">Anggaran</a> dulu agar bisa input biaya.
                </span>
            @else
                <a href="{{ route('operational.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Tambah Biaya
                </a>
            @endif
        </div>
    </div>

    {{-- Tabel --}}
    <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
        <table class="table table-bordered table-hover" id="dataTable" style="width: 100%; min-width: 800px;">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Usaha</th>
                    <th>Anggaran</th>
                    <th>Nama Biaya</th>
                    <th class="text-right">Jumlah</th>
                    <th style="width: 180px;">Tindakan</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<form action="" method="post" id="deleteForm">
    @csrf
    @method('DELETE')
    <input type="submit" value="Hapus" style="display:none;">
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    .op-page .summary-card { display: block; }
    @media (min-width: 768px) {
        .op-page .row.row-eq { display: flex; flex-wrap: wrap; }
        .op-page .row.row-eq > [class*='col-'] { display: flex; }
        .op-page .row.row-eq > [class*='col-'] > .summary-card { width: 100%; flex: 1; }
    }
    @media (max-width: 767px) {
        .op-page { padding: 10px !important; }
        .op-page .summary-card h3 { font-size: 18px !important; }
        .op-page .text-right { text-align: left !important; margin-top: 8px !important; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            float: none !important; text-align: left !important; margin-top: 8px;
        }
        .dataTables_filter input { width: 100% !important; margin-left: 0 !important; }
    }
</style>

<script>
    $(function(){
        $('#dataTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            order: [[0, 'desc']],
            ajax: "{{ route('operational.data') }}",
            columns: [
                {data: 'activity_date', name: 'activity_date'},
                {data: 'category', name: 'category', orderable: false},
                {data: 'budget', name: 'budget', orderable: false},
                {data: 'name', name: 'name'},
                {data: 'amount', name: 'amount', className: 'text-right'},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'}
            ]
        });
    });
</script>
@endpush
