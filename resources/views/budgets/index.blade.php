@extends('welcome')

@section('content')
    @if($over_budget ?? false)
        <div class="alert alert-danger" style="margin: 15px 20px 0;">
            Pengeluaran bulan ini melebihi plafon anggaran. Kurangi transaksi atau naikkan anggaran.
        </div>
    @endif

    <div class="row mt-5 justify-content-center" style="margin-top: 25px;">
        <div class="col-lg-4 col-md-6 col-sm-12" style="margin-left: 20px; margin-right: 20px; margin-top: 20px; width: calc(100% - 40px);">
            <div class="card shadow-lg sm-6 md-8 lg-12" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; ">
                <div class="card-body text-md-left" style="border: none; margin-left: 15px;">
                    <h5 class="card-title text-muted">Plafon anggaran (bulan ini)</h5>
                    <h2 class="font-weight-bold text-primary">
                        Rp {{ number_format($budget_cap, 0, ',', '.') }}
                    </h2>
                    <p class="mb-0 text-muted">
                        Terpakai: <strong>Rp {{ number_format($spent_this_month, 0, ',', '.') }}</strong><br>
                        Sisa: <strong>Rp {{ number_format($remaining_budget, 0, ',', '.') }}</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="box-header with-border  sm-6 md-8 lg-12" style="margin-bottom: -25px; margin-right: 10px;">
        <h3 class="box-title">.</h3>
        <div class="box-tools pull-right">
            <a href="{{ route('budgets.create') }}" 
               class="btn btn-primary btn-sm">
               <i class="fa fa-plus"></i> Tambah Anggaran
            </a>
        </div>
    </div>

     <!-- tabel -->
    <div class="box-body" style="margin-top: 100px;">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dataTable">
                <thead>
                    <tr>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                        <th>Kategori</th>
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
                ajax: "{{ route('budgets.data') }}",
                columns: [
                    {data: 'amount'},
                    {data: 'description'},
                    {data: 'category'},
                    {data: 'periode'},
                    {data: 'action'}
                ]
            });
        });
     </script>
@endpush