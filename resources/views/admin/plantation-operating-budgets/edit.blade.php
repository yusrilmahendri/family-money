@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Ubah Anggaran Kebun — {{ $entity->name }}</h3>
    <p class="text-muted">Perubahan dikirim ulang ke Plantation secara idempoten memakai public ID yang sama.</p>
    <form action="{{ route('admin.plantation-integrations.operating-budgets.update', [$entity, $budget]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')
        @include('admin.plantation-operating-budgets._form', ['budget' => $budget])
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.plantation-integrations.operating-budgets.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
