@extends('welcome')

@section('content')
    @if($over_budget ?? false)
        <div class="alert alert-danger" style="margin: 15px 15px 0;">
            Pengeluaran bulan ini melebihi plafon anggaran. Kurangi transaksi atau naikkan anggaran.
        </div>
    @endif

    <div class="budget-page" style="padding: 15px;">

        {{-- Ringkasan saldo & anggaran keseluruhan --}}
        <div class="row row-eq" style="margin-top: 15px;">
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Saldo</h6>
                    <h3 class="text-primary" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_saldo ?? 0, 0, ',', '.') }}
                    </h3>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Dialokasikan ke Anggaran</h6>
                    <h3 class="text-warning" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_dianggarkan ?? 0, 0, ',', '.') }}
                    </h3>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Transaksi Pribadi</h6>
                    <h3 class="text-danger" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_transaksi ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Misal: bayar BPJS, dll.</small>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Saldo Bebas (Tersisa)</h6>
                    <h3 class="{{ ($saldo_bebas ?? 0) < 0 ? 'text-danger' : 'text-success' }}" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($saldo_bebas ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Saldo − Anggaran − Transaksi</small>
                </div>
            </div>
        </div>

        {{-- Plafon bulan berjalan --}}
        <div class="row">
            <div class="col-xs-12" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Plafon Anggaran Bulan Ini</h6>
                    <h4 class="text-primary" style="font-weight: 700; margin: 0 0 5px; font-size: 20px;">
                        Rp {{ number_format($budget_cap, 0, ',', '.') }}
                    </h4>
                    <p class="text-muted" style="margin: 0; font-size: 13px;">
                        Terpakai: <strong>Rp {{ number_format($spent_this_month, 0, ',', '.') }}</strong>
                        &nbsp;|&nbsp;
                        Sisa: <strong>Rp {{ number_format($remaining_budget, 0, ',', '.') }}</strong>
                    </p>
                </div>
            </div>
        </div>

        {{-- Rincian per Jenis Usaha --}}
        @if(($rincian_per_kategori ?? collect())->isNotEmpty())
        <div class="row">
            <div class="col-xs-12" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                    <h5 style="font-weight: 700; margin-bottom: 15px; font-size: 16px;">Rincian per Jenis Usaha</h5>
                    <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
                        <table class="table table-striped" style="margin-bottom: 0; min-width: 700px;">
                            <thead style="background-color: #f8f9fa;">
                                <tr>
                                    <th>Jenis Usaha</th>
                                    <th class="text-right">Saldo</th>
                                    <th class="text-right">Anggaran</th>
                                    <th class="text-right">Trx Pribadi</th>
                                    <th class="text-right">Sisa Saldo</th>
                                    <th class="text-right">Aktivitas</th>
                                    <th class="text-right">Sisa Anggaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rincian_per_kategori as $row)
                                    <tr>
                                        <td><strong>{{ $row['name'] }}</strong></td>
                                        <td class="text-right">Rp {{ number_format($row['saldo'], 0, ',', '.') }}</td>
                                        <td class="text-right text-warning">Rp {{ number_format($row['anggaran'], 0, ',', '.') }}</td>
                                        <td class="text-right text-danger">Rp {{ number_format($row['transaksi'], 0, ',', '.') }}</td>
                                        <td class="text-right">
                                            <strong class="{{ $row['sisa_saldo'] < 0 ? 'text-danger' : 'text-success' }}">
                                                Rp {{ number_format($row['sisa_saldo'], 0, ',', '.') }}
                                            </strong>
                                        </td>
                                        <td class="text-right">Rp {{ number_format($row['aktivitas'], 0, ',', '.') }}</td>
                                        <td class="text-right">
                                            <strong class="{{ $row['sisa_anggaran'] < 0 ? 'text-danger' : 'text-primary' }}">
                                                Rp {{ number_format($row['sisa_anggaran'], 0, ',', '.') }}
                                            </strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted" style="display:block; margin-top: 8px;">
                        <strong>Sisa Saldo</strong> = Saldo − Anggaran − Transaksi Pribadi.
                        <strong>Sisa Anggaran</strong> = Anggaran − Aktivitas (upah, pupuk, dll).
                    </small>
                </div>
            </div>
        </div>
        @endif

        {{-- Header daftar anggaran --}}
        <div class="row" style="margin-top: 10px; margin-bottom: 10px;">
            <div class="col-xs-12 col-sm-7">
                <h3 style="margin: 5px 0; font-size: 18px;">Daftar Anggaran</h3>
            </div>
            <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
                <a href="{{ route('budgets.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Tambah Anggaran
                </a>
            </div>
        </div>

        {{-- tabel --}}
        <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
            <table class="table table-bordered table-hover" id="dataTable" style="min-width: 800px;">
                <thead>
                    <tr>
                        <th>Jenis Usaha</th>
                        <th>Anggaran</th>
                        <th>Terpakai (Aktivitas)</th>
                        <th>Sisa Anggaran</th>
                        <th>Keterangan</th>
                        <th>Periode</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- trigger pada menghapus data pengguna -->
    <form action="" method="post" id="deleteForm">
             @csrf
             @method("DELETE")
             <input type="submit" value="Hapus" style="display: none;">
    </form>
@endsection()

@push('scripts')
    <script src="{{ asset('js/bs-notify.min.js') }}"></script>
    @include('templates.partials.alerts')

    <style>
        .budget-page .summary-card { display: block; }
        @media (max-width: 767px) {
            .budget-page { padding: 10px !important; }
            .budget-page .summary-card h3 { font-size: 18px !important; }
            .budget-page .summary-card h4 { font-size: 16px !important; }
            .budget-page h3 { font-size: 16px; }
        }
        /* Equal-height row (Bootstrap 3 tidak punya bawaan ini) */
        @media (min-width: 768px) {
            .budget-page .row.row-eq {
                display: -webkit-flex;
                display: flex;
                flex-wrap: wrap;
            }
            .budget-page .row.row-eq > [class*='col-'] {
                display: -webkit-flex;
                display: flex;
            }
            .budget-page .row.row-eq > [class*='col-'] > .summary-card {
                width: 100%;
                -webkit-flex: 1;
                flex: 1;
            }
        }
        /* Datatable kontrol di mobile */
        @media (max-width: 767px) {
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                float: none !important;
                text-align: left !important;
                margin-top: 8px;
            }
        }
    </style>

    <script>
        $(function(){
            $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                autoWidth: false,
                ajax: "{{ route('budgets.data') }}",
                columns: [
                    {data: 'category'},
                    {data: 'amount'},
                    {data: 'terpakai'},
                    {data: 'sisa_anggaran'},
                    {data: 'description'},
                    {data: 'periode'},
                    {data: 'action'}
                ]
            });
        });
    </script>
@endpush
