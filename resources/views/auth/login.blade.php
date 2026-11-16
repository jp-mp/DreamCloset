@extends('layouts.account')

@section('title', 'Iniciar sesión')
@section('description', 'Entra para acceder a tu clóset y revisar el clima.')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="grid gap-4">
        @csrf

        <x-form-field name="email" label="Correo electrónico" type="email" :value="old('email')" autocomplete="username" required :autofocus="true" />
        <x-form-field name="password" label="Contraseña" type="password" autocomplete="current-password" required />

        <button type="submit" class="dc-action-primary mt-1">Ingresar</button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-center text-sm text-muted">
        ¿No tienes cuenta? <a href="{{ route('register') }}" class="rounded-sm font-bold text-ink underline underline-offset-4">Crear una cuenta</a>
    </p>
@endsection
