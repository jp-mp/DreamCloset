@props(['name', 'label', 'hint' => null, 'autofocus' => false])

@php
    $messages = $errors->get($name);
    $hasError = $messages !== [];
    $description = trim(($hint ? "{$name}-hint " : '').($hasError ? "{$name}-error" : ''));
    $shouldFocus = $errors->any() ? $errors->keys()[0] === $name : $autofocus;
@endphp

<div class="grid min-w-0 gap-1.5">
    <label for="{{ $name }}" class="text-sm font-bold">{{ $label }}</label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($description) aria-describedby="{{ $description }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($shouldFocus) autofocus @endif
        {{ $attributes->class([
            'min-h-12 w-full min-w-0 rounded-xl border px-3 py-2 text-base text-ink',
            'border-muted bg-white' => ! $hasError,
            'border-red-700 bg-red-50' => $hasError,
        ]) }}
    >

    @if ($hasError)
        <ul id="{{ $name }}-error" class="grid gap-1 text-sm font-medium text-red-800" role="list">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</div>
