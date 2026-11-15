@extends('layouts.account')
@section('title', 'Iniciar sesión')
@section('content')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Ingresar</button>
    </form>
    <p>¿No tienes cuenta? <a href="{{ route('register') }}">Crear una cuenta</a></p>
@endsection
