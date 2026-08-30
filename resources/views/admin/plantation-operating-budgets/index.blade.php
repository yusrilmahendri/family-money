@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Anggaran Kebun — {{ $entity->name }}</h3>
    <p class="text-muted">
        Anggaran operasional kebun baru. Histori tabel <code>budgets</code> (anggaran per kategori) tidak ikut disinkronkan.
        Plantation hanya menerima kontrak alokasi; struktur tabel Finance tidak dibagikan.
    </p>

    <p>
        <a href="{{ route('admin.plantation-integrations.operating-budgets.create', $entity) }}" class="btn btn-primary">Buat anggaran kebun</a>
        <a href="{{ route('admin.plantation-integrations.show', $entity) }}" class="btn btn-default">Kembali</a>
    </p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Periode</th>
                    <th>Alokasi</th>
                    <th>Status</th>
                    <th>Public ID</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($budgets as $budget)
                    <tr>
                        <td>{{ $budget->name }}</td>
                        <td>{{ $budget->period_start->format('Y-m-d') }} s/d {{ $budget->period_end->format('Y-m-d') }}</td>
                        <td>{{ rupiah($budget->allocated_amount) }}</td>
                        <td>
                            {{ $budget->status->value }}
                            @if($budget->last_error)
                                <div class="text-danger" style="font-size:12px;">{{ $budget->last_error }}</div>
                            @endif
                        </td>
                        <td><code>{{ $budget->public_id }}</code></td>
                        <td>
                            <a href="{{ route('admin.plantation-integrations.operating-budgets.edit', [$entity, $budget]) }}" class="btn btn-xs btn-default">Ubah</a>
                            <form action="{{ route('admin.plantation-integrations.operating-budgets.sync', [$entity, $budget]) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-info">Kirim ulang</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Belum ada anggaran kebun. Anggaran kategori lama tidak muncul di sini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $budgets->links() }}
@endsection
