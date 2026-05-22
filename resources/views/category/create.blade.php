@extends('welcome')

@section('content')
<div style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <h4 style="margin: 5px 0 15px;">Tambah Jenis Usaha</h4>
            <p class="text-muted">Tambahkan jenis usaha Anda, misal: <em>Usaha Kebun Sawit, Usaha Warung, Usaha Ternak</em>, dll.</p>

            <form action="{{ route('categories.store') }}" method="POST">
                @csrf

                <div class="form-group @error('name') has-error @enderror">
                    <label for="name">Nama Jenis Usaha <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name"
                        name="name" value="{{ old('name') }}"
                        placeholder="Misal: Usaha Kebun Sawit" required autofocus/>
                    @error('name')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Simpan Data</button>
                <a href="{{ route('categories.index') }}" class="btn btn-default">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection
