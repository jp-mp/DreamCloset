@props(['loading'])

<div {{ $attributes }}>
    <p data-status tabindex="-1" role="status" class="text-sm leading-relaxed">{{ $loading }}</p>
    <button type="button" data-retry hidden class="mt-3 cursor-pointer rounded-sm text-sm font-bold underline underline-offset-4">Reintentar</button>
    <a href="{{ route('login') }}" data-sign-in hidden class="mt-3 rounded-sm text-sm font-bold underline underline-offset-4">Ir al inicio de sesión</a>
    {{ $slot }}
</div>
