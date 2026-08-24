@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Laporan Konsolidasi</h3>
    <p class="text-muted">Setiap entity ditampilkan terpisah. Angka FAMILY dan BUSINESS tidak digabung menjadi satu total bisnis.</p>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="form-inline" style="margin-bottom:16px;">
        <div class="form-group" style="margin-right:8px;">
            <label for="finance_entity_id">Entity</label>
            <select name="finance_entity_id" id="finance_entity_id" class="form-control">
                <option value="">Semua</option>
                @foreach($entities as $item)
                    <option value="{{ $item->id }}" @selected((string) $filters['finance_entity_id'] === (string) $item->id)>
                        {{ $item->name }} ({{ $item->type->value }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-right:8px;">
            <label for="type">Tipe</label>
            <select name="type" id="type" class="form-control">
                <option value="">Semua</option>
                <option value="FAMILY" @selected($filters['type'] === 'FAMILY')>FAMILY</option>
                <option value="BUSINESS" @selected($filters['type'] === 'BUSINESS')>BUSINESS</option>
            </select>
        </div>
        <div class="form-group" style="margin-right:8px;">
            <label for="from">Dari</label>
            <input id="from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
        </div>
        <div class="form-group" style="margin-right:8px;">
            <label for="to">Sampai</label>
            <input id="to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-default btn-sm">Reset</a>
    </form>

    <h4>FAMILY</h4>
    <div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Entity</th>
                <th>Saldo</th>
                <th>Pengeluaran</th>
                <th>Modal ke usaha</th>
                <th>Prive diterima</th>
            </tr>
        </thead>
        <tbody>
        @forelse($familyRows as $row)
            <tr>
                <td>{{ $row['entity_name'] }} <span class="label label-info">FAMILY</span></td>
                <td>Rp {{ number_format($row['balance_total'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['family']['pengeluaran'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['family']['modal_ke_usaha'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['family']['penerimaan_prive'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">Tidak ada FAMILY pada filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <h4>BUSINESS</h4>
    <div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Entity</th>
                <th>Saldo</th>
                <th>Revenue</th>
                <th>Laba / Rugi</th>
                <th>Modal diterima</th>
                <th>Prive</th>
            </tr>
        </thead>
        <tbody>
        @forelse($businessRows as $row)
            <tr>
                <td>{{ $row['entity_name'] }} <span class="label label-success">BUSINESS</span></td>
                <td>Rp {{ number_format($row['balance_total'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['business']['revenue'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['business']['profit'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['business']['capital_received'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row['business']['prive'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">Tidak ada BUSINESS pada filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
@endsection
