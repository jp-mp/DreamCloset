@extends('layouts.account')

@section('title', 'Crear una cuenta')
@section('description', 'Crea tu cuenta para organizar las prendas que ya tienes.')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="grid gap-4">
        @csrf

        <x-form-field name="name" label="Nombre" :value="old('name')" maxlength="100" autocomplete="name" required :autofocus="true" />
        <x-form-field name="email" label="Correo electrónico" type="email" :value="old('email')" maxlength="255" autocomplete="username" required />
        <x-form-field name="password" label="Contraseña" type="password" hint="Mínimo 8 caracteres." minlength="8" autocomplete="new-password" required />
        <x-form-field name="password_confirmation" label="Confirmar contraseña" type="password" minlength="8" autocomplete="new-password" required />

        <button type="submit" class="dc-action-primary mt-1">Crear cuenta</button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-center text-sm text-muted">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}" class="rounded-sm font-bold text-ink underline underline-offset-4">Iniciar sesión</a>
    </p>
@endsection
