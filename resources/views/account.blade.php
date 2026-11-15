@extends('layouts.account')
@section('title', 'Mi cuenta')
@section('content')
    <p>Hola, {{ $user->name }}.</p>
    <p>{{ $user->email }}</p>
    <h2>Ubicación guardada</h2>
    @if ($user->latitude !== null && $user->longitude !== null)
        <p>{{ $user->location_name ?? 'Mi ubicación' }}</p>
    @else
        <p>Aún no has guardado una ubicación.</p>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
@endsection
