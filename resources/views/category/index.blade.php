@extends('welcome')

@section('content')

    <div class="category-page" style="padding: 15px;">
        <div class="row" style="margin-bottom: 10px;">
            <div class="col-xs-12 col-sm-7">
                <h3 style="margin: 5px 0; font-size: 18px;">Daftar Jenis Usaha</h3>
                <p class="text-muted" style="margin: 0; font-size: 13px;">
                    Kelola jenis usaha (kategori) untuk saldo, anggaran, &amp; pemasukan.
                </p>
            </div>
            <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
                <a href="{{ route('categories.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Tambah Jenis Usaha
                </a>
            </div>
        </div>

        {{-- tabel --}}
        <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
            <table class="table table-bordered table-hover" id="dataTable" style="width: 100%; min-width: 360px;">
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th>Nama Jenis Usaha</th>
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
        .category-page .table thead th {
            background-color: #f8f9fa;
            white-space: nowrap;
        }
        .category-page .table td,
        .category-page .table th {
            vertical-align: middle;
        }
        @media (max-width: 767px) {
            .category-page { padding: 10px !important; }
            .category-page h3 { font-size: 16px; }
            .category-page .text-right { text-align: left !important; margin-top: 8px !important; }

            /* DataTables kontrol tidak menumpuk di mobile */
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
    </style>

 <script>
        $(function(){
            $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                autoWidth: false,
                ajax: "{{ route('categories.data') }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center'},
                    {data: 'name', name: 'name'},
                    {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'}
                ]
            });
        });
     </script>
@endpush
