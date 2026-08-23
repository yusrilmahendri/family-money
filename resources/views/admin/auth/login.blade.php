@extends('admin.layouts.guest')

@section('content')
    <div class="login-card">
        <h1><i class="fa fa-lock"></i> Admin Login</h1>
        <p class="sub">Hanya akun administrator yang dapat masuk.</p>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <div class="form-group @error('email') has-error @enderror">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control"
                    value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="form-group @error('password') has-error @enderror">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control"
                    required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-login">Masuk</button>
        </form>
    </div>
@endsection
