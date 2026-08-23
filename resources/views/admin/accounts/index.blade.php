@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">Kas & Rekening — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Account milik entity ini. Saldo awal bukan saldo berjalan. Tidak ada hard delete.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            <a href="{{ route('admin.finance-entities.accounts.create', $entity) }}" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Tambah Account
            </a>
            <a href="{{ route('admin.finance-entities.transfers.index', $entity) }}" class="btn btn-default btn-sm">
                Transfer
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>Bank</th>
                    <th>Nomor</th>
                    <th>Default</th>
                    <th>Status</th>
                    <th style="width: 280px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->type->label() }}</td>
                        <td>{{ $account->bank_name ?: '—' }}</td>
                        <td>{{ $account->maskedAccountNumber() }}</td>
                        <td>
                            @if($account->is_default)
                                <span class="label label-info">Default</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($account->is_active)
                                <span class="label label-success">Aktif</span>
                            @else
                                <span class="label label-default">Nonaktif</span>
                            @endif
                        </td>
                        <td class="admin-actions">
                            <a href="{{ route('admin.finance-entities.accounts.edit', [$entity, $account]) }}" class="btn btn-default btn-xs">Edit</a>
                            @if($account->is_active)
                                @unless($account->is_default)
                                    <form action="{{ route('admin.finance-entities.accounts.set-default', [$entity, $account]) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-info btn-xs">Jadikan default</button>
                                    </form>
                                @endunless
                                <form action="{{ route('admin.finance-entities.accounts.deactivate', [$entity, $account]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs">Nonaktifkan</button>
                                </form>
                            @else
                                <form action="{{ route('admin.finance-entities.accounts.activate', [$entity, $account]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs">Aktifkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada account.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
