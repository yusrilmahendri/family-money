@extends('welcome')

@section('content')

    <div style="padding: 15px;">
        <div class="row" style="margin-bottom: 10px;">
            <div class="col-xs-12 col-sm-7">
                <h3 style="margin: 5px 0; font-size: 18px;">Daftar Jenis Usaha</h3>
            </div>
            <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
                <a href="{{ route('categories.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Tambah Jenis Usaha
                </a>
            </div>
        </div>

        <!-- tabel -->
        <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
            <table class="table table-bordered table-hover" id="dataTable" style="min-width: 400px;">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Jenis Usaha</th>
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


 <script>
        $(function(){
            $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('categories.data') }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                    {data: 'name', name: 'name'},
                    {data: 'action', name: 'action', orderable: false, searchable: false}
                ]
            });
        });
     </script>
@endpush
