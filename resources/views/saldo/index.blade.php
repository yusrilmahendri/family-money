@extends('welcome')

@section('content')

    <div class="saldo-page" style="padding: 15px;">

        {{-- Ringkasan global (sinkron dengan halaman Anggaran) --}}
        <div class="row row-eq" style="margin-top: 5px;">
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Saldo Manual</h6>
                    <h3 class="text-primary" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_saldo_manual ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Dari halaman Saldo</small>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Pemasukan Usaha</h6>
                    <h3 class="text-info" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_pemasukan ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Dari menu Pemasukan Usaha</small>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Dana</h6>
                    <h3 class="text-success" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($total_dana ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Saldo + Pemasukan</small>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                    <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Saldo Bebas</h6>
                    <h3 class="{{ ($saldo_bebas ?? 0) < 0 ? 'text-danger' : 'text-success' }}" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                        Rp {{ number_format($saldo_bebas ?? 0, 0, ',', '.') }}
                    </h3>
                    <small class="text-muted">Dana − Anggaran − Trx Pribadi</small>
                </div>
            </div>
        </div>

        {{-- Update terakhir & info kategori dinamis --}}
        <div class="row">
            <div class="col-xs-12 col-md-6" style="margin-bottom: 15px;">
                <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                    <h6 class="text-muted" style="margin: 0 0 5px; font-size: 13px;">Update Terakhir</h6>
                    <p style="margin: 0; font-size: 14px;">
                        {{ $updated_saldo ? \Carbon\Carbon::parse($updated_saldo->periode_saldo)->translatedFormat('d F Y') : '-' }}
                    </p>
                </div>
            </div>
            <div class="col-xs-12 col-md-6" id="categoryCardWrap" style="display: none; margin-bottom: 15px;">
                <div id="categoryCard" class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                    <h6 class="text-muted" style="margin: 0 0 6px; font-size: 13px;">
                        Kategori: <span id="categoryName" style="font-weight: 700; color: #333;"></span>
                    </h6>
                    <h3 class="text-success" style="font-weight: 700; margin: 0; font-size: 20px;">
                        <span id="categorySaldo">Rp 0</span>
                    </h3>
                    <small class="text-muted">Total saldo manual untuk kategori ini</small>
                </div>
            </div>
        </div>
    </div>

    <div class="saldo-page" style="padding: 0 15px;">
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-xs-12 col-lg-7" style="margin-bottom: 10px;">
                <label for="categoryFilter" style="font-weight: 600; margin-bottom: 8px;">Filter Berdasarkan Kategori</label>
                <select class="form-control" id="categoryFilter" style="border-radius: 8px;">
                    <option value="">-- Pilih Kategori Saldo --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" data-name="{{ $category->name }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-xs-12 col-lg-5" style="margin-bottom: 10px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Aksi</label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="{{ route('saldos.export.excel') }}"
                       class="btn btn-success btn-sm"
                       style="flex: 1; min-width: 100px; margin-bottom: 5px;">
                       <i class="fa fa-file-excel-o"></i> Excel
                    </a>
                    <a href="{{ route('saldos.export.pdf') }}"
                       class="btn btn-danger btn-sm"
                       target="_blank"
                       style="flex: 1; min-width: 100px; margin-bottom: 5px;">
                       <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                    <a href="{{ route('saldos.create') }}"
                       class="btn btn-primary btn-sm"
                       style="flex: 1; min-width: 100px; margin-bottom: 5px;">
                       <i class="fa fa-plus"></i> Tambah
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Saldo -->
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">Detail Saldo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalDetailContent">
                    <div class="text-center text-muted">Memuat data...</div>
                </div>
            </div>
        </div>
    </div>
     <!-- tabel -->
    <div class="saldo-page" style="padding: 0 15px;">
        <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
            <table class="table table-bordered table-hover" id="dataTable" style="width: 100%; min-width: 700px;">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Saldo</th>
                        <th>Keterangan</th>
                        <th>Nota</th>
                        <th>Tanggal &amp; Waktu Input</th>
                        <th style="width: 180px;">Tindakan</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
      <!-- trigger pada menghapus data pengguna -->
    <form action="" method="post" id="deleteForm">
             @csrf
             @method("DELETE")
             <input type="submit" value="Hapus"
             style="display: none ">
    </form>
@endsection()

@push('scripts')
     <!-- boostrap notify -->
     <script src="{{ asset('js/bs-notify.min.js') }}">
     </script>

    <!-- alertnya boostrap notify -->
    @include('templates.partials.alerts')

    <style>
        .saldo-page .summary-card { display: block; }
        @media (max-width: 767px) {
            .saldo-page { padding: 10px !important; }
            .saldo-page .summary-card h3 { font-size: 18px !important; }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                float: none !important;
                text-align: left !important;
                margin-top: 8px;
            }
            .dataTables_filter input { width: 100% !important; margin-left: 0 !important; }
        }
        /* Equal-height row (Bootstrap 3 tidak punya bawaan ini) */
        @media (min-width: 768px) {
            .saldo-page .row.row-eq {
                display: -webkit-flex;
                display: flex;
                flex-wrap: wrap;
            }
            .saldo-page .row.row-eq > [class*='col-'] {
                display: -webkit-flex;
                display: flex;
            }
            .saldo-page .row.row-eq > [class*='col-'] > .summary-card {
                width: 100%;
                -webkit-flex: 1;
                flex: 1;
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
                ajax: "{{ route('saldos.data') }}",
                columns: [
                    {data: 'category'},
                    {data: 'amount'},
                    {data: 'description'},
                    {data: 'nota_image', orderable: false, searchable: false},
                    {data: 'periode_saldo'},
                    {data: 'action'}
                ]
            });

            // Format Rupiah function
            function formatRupiah(angka) {
                return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            // Handle category filter
            $('#categoryFilter').on('change', function() {
                const categoryId = $(this).val();
                const categoryName = $(this).find('option:selected').data('name');

                if (categoryId) {
                    fetch(`/api/v1/saldos/category/${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            $('#categoryName').text(categoryName);
                            $('#categorySaldo').text(formatRupiah(data.total));
                            $('#categoryCardWrap').slideDown();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Gagal memuat data kategori');
                        });
                } else {
                    $('#categoryCardWrap').slideUp();
                }
            });
        });
     </script>
@endpush
