@extends('welcome')

@section('content')
<div class="income-page" style="padding: 15px;">

    {{-- Ringkasan --}}
    <div class="row row-eq" style="margin-top: 15px;">
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Total Pemasukan</h6>
                <h3 class="text-success" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($total_income ?? 0, 0, ',', '.') }}
                </h3>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Pemasukan Bulan Ini</h6>
                <h3 class="text-primary" style="font-weight: 700; margin: 0; font-size: 22px; word-break: break-all;">
                    Rp {{ number_format($this_month ?? 0, 0, ',', '.') }}
                </h3>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h6 class="text-muted" style="margin: 0 0 8px; font-size: 13px;">Jumlah Jenis Usaha Aktif</h6>
                <h3 class="text-info" style="font-weight: 700; margin: 0; font-size: 22px;">
                    {{ count($per_kategori ?? []) }}
                </h3>
                <small class="text-muted">yang punya pemasukan</small>
            </div>
        </div>
    </div>

    @if(($per_kategori ?? collect())->isNotEmpty())
    <div class="row">
        <div class="col-xs-12" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 15px; background: #fff;">
                <h5 style="font-weight: 700; margin-bottom: 15px; font-size: 16px;">Pemasukan per Jenis Usaha</h5>
                <div class="table-responsive">
                    <table class="table table-striped" style="margin-bottom: 0;">
                        <thead style="background: #f8f9fa;">
                            <tr>
                                <th>Jenis Usaha</th>
                                <th class="text-right">Bulan Ini</th>
                                <th class="text-right">Total Sepanjang Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($per_kategori as $row)
                                <tr>
                                    <td><strong>{{ $row['name'] }}</strong></td>
                                    <td class="text-right">Rp {{ number_format($row['this_month'], 0, ',', '.') }}</td>
                                    <td class="text-right text-success"><strong>Rp {{ number_format($row['total'], 0, ',', '.') }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row" style="margin-bottom: 10px;">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0; font-size: 18px;">Daftar Pemasukan</h3>
        </div>
        <div class="col-xs-12 col-sm-5 text-right" style="margin-top: 5px;">
            <a href="{{ route('incomes.create') }}" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> Tambah Pemasukan
            </a>
        </div>
    </div>

    <div class="table-responsive" style="-webkit-overflow-scrolling: touch;">
        <table class="table table-bordered table-hover" id="dataTable" style="min-width: 800px;">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Usaha</th>
                    <th>Sumber</th>
                    <th>Jumlah</th>
                    <th>Keterangan</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<form action="" method="post" id="deleteForm">
    @csrf
    @method("DELETE")
    <input type="submit" value="Hapus" style="display: none;">
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    @media (max-width: 767px) {
        .income-page { padding: 10px !important; }
        .income-page .summary-card h3 { font-size: 18px !important; }
    }
    @media (min-width: 768px) {
        .income-page .row.row-eq { display: flex; flex-wrap: wrap; }
        .income-page .row.row-eq > [class*='col-'] { display: flex; }
        .income-page .row.row-eq > [class*='col-'] > .summary-card { width: 100%; flex: 1; }
    }
</style>

<script>
    $(function(){
        $('#dataTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: "{{ route('incomes.data') }}",
            order: [[0, 'desc']],
            columns: [
                {data: 'income_date'},
                {data: 'category'},
                {data: 'source'},
                {data: 'amount'},
                {data: 'description'},
                {data: 'action'}
            ]
        });
    });
</script>
@endpush
