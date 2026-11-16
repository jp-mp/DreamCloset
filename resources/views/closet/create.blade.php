@extends('layouts.app')

@section('title', 'Agregar prenda')

@section('content')
    <div class="grid max-w-2xl gap-6">
        <a href="{{ route('closet.index') }}" class="w-fit rounded-sm text-sm font-bold underline underline-offset-4">Volver a Mi clóset</a>
        <h1 class="font-heading text-display font-semibold tracking-tight">Agregar prenda</h1>

        <x-panel>
            <form method="POST" action="{{ route('clothing.store', absolute: false) }}" data-garment-form data-return-url="{{ route('closet.index') }}" class="grid gap-5">
                @csrf

                <x-form-field name="name" label="Nombre de la prenda" maxlength="100" placeholder="Por ejemplo, Chaqueta de mezclilla" required />
                <x-form-field name="category" label="Categoría" :options="['' => 'Elige una categoría', 'top' => 'Parte superior', 'bottom' => 'Parte inferior', 'outerwear' => 'Abrigo', 'footwear' => 'Calzado']" required />
                <x-form-field name="color" label="Color (opcional)" maxlength="50" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form-field name="season" label="Temporada" :options="['all' => 'Todas las temporadas', 'spring' => 'Primavera', 'summer' => 'Verano', 'autumn' => 'Otoño', 'winter' => 'Invierno']" required />
                    <x-form-field name="warmth" label="Nivel de abrigo" :options="['' => 'Elige un nivel', 'light' => 'Liviano', 'medium' => 'Medio', 'warm' => 'Abrigado']" required />
                </div>

                <label class="flex w-fit cursor-pointer items-center gap-2 text-sm font-bold">
                    <input type="checkbox" name="waterproof" value="1" class="size-5 accent-ink">
                    Impermeable
                </label>

                <p data-save-status role="status" class="text-sm text-muted empty:hidden"></p>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="dc-action-primary disabled:opacity-60">Guardar prenda</button>
                    <a href="{{ route('closet.index') }}" class="dc-action-secondary">Cancelar</a>
                </div>
            </form>
        </x-panel>
    </div>
@endsection
