@extends('admin.layouts.guest')

@section('content')
    <div class="login-card">
        <h1>Masuk Admin</h1>
        <p class="sub">Gunakan akun administrator untuk membuka panel Admin.</p>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <div class="form-group @error('email') has-error @enderror">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control"
                    value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group @error('password') has-error @enderror">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control"
                    required autocomplete="current-password">
                @error('password') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-login">Masuk</button>
        </form>
    </div>
@endsection
