@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Dashboard</h3>
    <p class="text-muted">Ringkasan Finance Entity. Laporan transaksi dan saldo tidak ditampilkan di sini.</p>

    <div class="row">
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="admin-stat">
                <span class="label-stat">Total Entity</span>
                <div class="value-stat">{{ $totalEntities }}</div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="admin-stat">
                <span class="label-stat">FAMILY</span>
                <div class="value-stat">{{ $familyCount }}</div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="admin-stat">
                <span class="label-stat">BUSINESS</span>
                <div class="value-stat">{{ $businessCount }}</div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="admin-stat">
                <span class="label-stat">Aktif</span>
                <div class="value-stat">{{ $activeCount }}</div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="admin-stat">
                <span class="label-stat">Nonaktif</span>
                <div class="value-stat">{{ $inactiveCount }}</div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.finance-entities.index') }}" class="btn btn-primary">
        <i class="fa fa-building"></i> Kelola Finance Entities
    </a>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-default">
        <i class="fa fa-bar-chart"></i> Laporan Konsolidasi
    </a>
    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-default">
        <i class="fa fa-list-alt"></i> Audit Logs
    </a>
@endsection
