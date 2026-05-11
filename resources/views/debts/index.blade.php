@extends('welcome')

@section('content')
<div class="main" style="margin-top: 40px;">
    <div class="clearfix" style="margin-bottom: 15px;">
        <h3 class="pull-left">Utang &amp; cicilan</h3>
        <a href="{{ route('debts.create') }}" class="btn btn-primary pull-right"><em class="fa fa-plus"></em> Tambah</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Pokok</th>
                    <th>Sisa</th>
                    <th>Cicilan / bln</th>
                    <th>Jatuh tempo (tanggal)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($debts as $d)
                    <tr>
                        <td>{{ $d->title }}</td>
                        <td>Rp {{ number_format((float) $d->principal_total, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $d->remaining_balance, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $d->monthly_installment, 0, ',', '.') }}</td>
                        <td>{{ $d->due_day ? 'Tgl '.$d->due_day : '—' }}</td>
                        <td>
                            <a href="{{ route('debts.show', $d) }}" class="btn btn-info btn-xs">Detail / bayar</a>
                            <a href="{{ route('debts.edit', $d) }}" class="btn btn-warning btn-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
