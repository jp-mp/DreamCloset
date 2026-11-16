@extends('layouts.app')

@php
    $item = $item ?? null;
    $title = $item ? 'Editar prenda' : 'Agregar prenda';
@endphp

@section('title', $title)

@section('content')
    <div class="grid max-w-2xl gap-6">
        <a href="{{ route('closet.index') }}" class="w-fit rounded-sm text-sm font-bold underline underline-offset-4">Volver a Mi clóset</a>
        <h1 class="font-heading text-display font-semibold tracking-tight">{{ $title }}</h1>

        <x-panel>
            <form method="POST" enctype="multipart/form-data"
                action="{{ $item ? route('clothing.update', $item, absolute: false) : route('clothing.store', absolute: false) }}"
                data-garment-form data-photo-endpoint="{{ route('clothing.photo-preview', absolute: false) }}"
                data-saved-photo="{{ $item?->photo_path ? route('clothing.photo', $item, absolute: false) : '' }}"
                data-return-url="{{ route('closet.index') }}" class="grid gap-5">
                @csrf
                @if ($item)
                    @method('PUT')
                @endif

                <x-form-field name="name" :value="$item?->name" label="Nombre de la prenda" maxlength="100" placeholder="Por ejemplo, Chaqueta de mezclilla" required />
                <x-form-field name="category" :value="$item?->category" label="Categoría" :options="['' => 'Elige una categoría', ...\App\Models\ClothingItem::CATEGORIES]" required />
                <x-form-field name="color" :value="$item?->color" label="Color (opcional)" maxlength="50" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form-field name="season" :value="$item?->season ?? 'all'" label="Temporada" :options="['all' => 'Todas las temporadas', 'spring' => 'Primavera', 'summer' => 'Verano', 'autumn' => 'Otoño', 'winter' => 'Invierno']" required />
                    <x-form-field name="warmth" :value="$item?->warmth" label="Nivel de abrigo" :options="['' => 'Elige un nivel', 'light' => 'Liviano', 'medium' => 'Medio', 'warm' => 'Abrigado']" required />
                </div>

                <x-form-field name="style" :value="$item?->style" label="Estilo" :options="['' => 'Cualquier estilo', ...\App\Models\ClothingItem::STYLES]" />

                <label class="flex w-fit cursor-pointer items-center gap-2 text-sm font-bold">
                    <input type="checkbox" name="waterproof" value="1" @checked($item?->waterproof) class="size-5 accent-ink">
                    Impermeable
                </label>

                <fieldset class="grid min-w-0 gap-3 rounded-card border border-line bg-canvas p-4">
                    <legend class="px-2 text-sm font-bold">Foto de la prenda (opcional)</legend>
                    <label for="photo" class="sr-only">{{ $item?->photo_path ? 'Reemplazar foto' : 'Seleccionar foto' }}</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help" class="w-full text-sm file:mr-3 file:rounded-lg file:border file:border-line file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold">
                    <p id="photo-help" class="text-xs text-muted">
                        JPG, PNG o WebP de hasta 5 MB. Usa «Quitar fondo» para preparar la foto para el outfit.
                    </p>

                    <div data-photo-previews hidden class="grid gap-3 sm:grid-cols-2">
                        <figure class="min-w-0 overflow-hidden rounded-xl border border-line bg-white">
                            <img data-photo-original class="aspect-square w-full object-contain dc-photo-transparency" alt="Foto original de la prenda">
                            <figcaption class="flex flex-wrap items-center justify-between gap-2 p-2 text-sm">
                                <span data-photo-source-label>Original</span>
                                <button data-choose-original type="button" class="rounded-md px-2 py-1 font-bold underline underline-offset-4 aria-pressed:bg-canvas aria-pressed:no-underline" aria-pressed="false" hidden>Usar original</button>
                            </figcaption>
                        </figure>
                        <figure data-photo-cutout hidden class="min-w-0 overflow-hidden rounded-xl border border-line bg-white">
                            <img class="aspect-square w-full object-contain dc-photo-transparency" alt="Prenda con el fondo eliminado">
                            <figcaption class="flex flex-wrap items-center justify-between gap-2 p-2 text-sm">
                                Sin fondo
                                <button data-choose-cutout type="button" class="rounded-md px-2 py-1 font-bold underline underline-offset-4 aria-pressed:bg-canvas aria-pressed:no-underline" aria-pressed="false">Usar recorte</button>
                            </figcaption>
                        </figure>
                    </div>

                    <div data-photo-actions hidden class="flex flex-wrap gap-2">
                        <button data-remove-background type="button" class="dc-action-secondary bg-white disabled:opacity-60">Quitar fondo</button>
                        <button data-remove-photo type="button" class="rounded-lg px-3 text-sm font-bold underline underline-offset-4">Quitar foto</button>
                    </div>
                    <button data-restore-photo type="button" hidden class="w-fit rounded-lg px-3 py-2 text-sm font-bold underline underline-offset-4">Restaurar foto guardada</button>
                    <p data-photo-status role="status" class="text-sm text-muted empty:hidden"></p>
                </fieldset>

                <p data-save-status role="status" class="text-sm text-muted empty:hidden"></p>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="dc-action-primary disabled:opacity-60">{{ $item ? 'Guardar cambios' : 'Guardar prenda' }}</button>
                    <a href="{{ route('closet.index') }}" class="dc-action-secondary">Cancelar</a>
                </div>
            </form>
        </x-panel>
    </div>
@endsection
