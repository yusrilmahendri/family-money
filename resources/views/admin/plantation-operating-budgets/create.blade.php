@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Buat Anggaran Kebun — {{ $entity->name }}</h3>
    <p class="text-muted">Anggaran ini milik Finance. Setelah aktif, Plantation menerima alokasi dengan jumlah yang sama dan boleh membaginya ke item operasional.</p>
    <form action="{{ route('admin.plantation-integrations.operating-budgets.store', $entity) }}" method="POST" class="admin-form">
        @csrf
        @include('admin.plantation-operating-budgets._form')
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">Simpan dan kirim ke Plantation</button>
            <a href="{{ route('admin.plantation-integrations.operating-budgets.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
