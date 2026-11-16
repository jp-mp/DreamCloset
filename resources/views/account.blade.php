@extends('layouts.app')

@section('title', 'Mi cuenta')

@section('content')
    <div class="grid max-w-2xl gap-6">
        <div class="grid gap-3">
            <p class="text-xs font-bold tracking-widest text-muted uppercase">Tu perfil</p>
            <h1 class="font-heading text-display font-semibold tracking-tight">Mi cuenta</h1>
            <p class="wrap-anywhere text-muted">Hola, {{ $user->name }}.</p>
        </div>

        <x-panel aria-labelledby="datos-cuenta">
            <h2 id="datos-cuenta" class="font-heading text-3xl leading-tight font-semibold">Datos de tu cuenta</h2>

            <dl class="mt-5 grid gap-4">
                <div class="grid gap-1">
                    <dt class="text-sm font-bold">Correo electrónico</dt>
                    <dd class="wrap-anywhere text-muted">{{ $user->email }}</dd>
                </div>
                <div class="grid gap-1 border-t border-line pt-4">
                    <dt class="text-sm font-bold">Ubicación guardada</dt>
                    <dd class="wrap-anywhere text-muted">
                        @if ($user->latitude !== null && $user->longitude !== null)
                            {{ $user->location_name ?? 'Mi ubicación' }}
                        @else
                            Aún no has guardado una ubicación.
                        @endif
                    </dd>
                </div>
            </dl>
        </x-panel>

        <div class="grid gap-3 sm:flex sm:flex-wrap">
            <a href="{{ route('home') }}" class="dc-action-primary">Ir a Inicio</a>
            <form method="POST" action="{{ route('logout') }}" class="grid">
                @csrf
                <button type="submit" class="dc-action-secondary">Cerrar sesión</button>
            </form>
        </div>
    </div>
@endsection
