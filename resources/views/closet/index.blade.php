@extends('layouts.app')

@section('title', 'Mi clóset')

@section('content')
    @php
        $categories = [
            'top' => 'Parte superior',
            'bottom' => 'Parte inferior',
            'outerwear' => 'Abrigo',
            'footwear' => 'Calzado',
        ];
        $requestedCategory = request()->query('category');
        $category = is_string($requestedCategory) && isset($categories[$requestedCategory]) ? $requestedCategory : '';
    @endphp

    <div data-closet="{{ route('clothing.index', absolute: false) }}"
        data-delete-url="{{ route('clothing.destroy', ['clothingItem' => '__item__'], absolute: false) }}"
        data-style-url="{{ route('clothing.style', ['clothingItem' => '__item__'], absolute: false) }}"
        data-csrf="{{ csrf_token() }}" data-category="{{ $category }}" class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="grid gap-3">
                <p class="text-xs font-bold tracking-widest text-muted uppercase">Tu espacio personal</p>
                <h1 class="font-heading text-display font-semibold tracking-tight">Mi clóset</h1>
                <p class="text-muted">Todas tus prendas, organizadas en un solo lugar.</p>
            </div>
            <a href="{{ route('closet.create') }}" class="dc-action-primary">Agregar prenda</a>
        </header>

        <nav aria-label="Categorías de prendas" class="mt-2 border-b border-line">
            <ul class="flex flex-wrap gap-x-4 sm:gap-x-6">
                @foreach (['' => 'Todo', ...$categories] as $value => $label)
                    <li class="flex">
                        <a
                            href="{{ route('closet.index', $value === '' ? [] : ['category' => $value]) }}"
                            data-category-link="{{ $value }}"
                            @if ($category === $value) aria-current="page" @endif
                            class="flex min-h-11 items-center border-b-2 border-transparent text-sm font-medium text-muted hover:text-ink aria-[current=page]:border-accent aria-[current=page]:font-bold aria-[current=page]:text-ink"
                        >{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="grid gap-5">
            <x-data-status loading="Cargando tus prendas…" />
            <section data-closet-empty hidden class="rounded-panel border border-line bg-white px-6 py-12 text-center sm:py-16">
                @if ($category)
                    <h2 class="font-heading text-4xl font-semibold">Todavía no hay prendas en esta categoría</h2>
                    <a href="{{ route('closet.index') }}" class="dc-action-secondary mt-5">Ver todas las prendas</a>
                @else
                    <h2 class="font-heading text-4xl font-semibold">Tu clóset está vacío</h2>
                    <p class="mt-2 text-muted">Las prendas que registres aparecerán aquí.</p>
                @endif
            </section>
            <ul data-closet-items aria-label="Prendas del clóset" hidden class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"></ul>
            <nav data-closet-pagination aria-label="Páginas de prendas" hidden class="flex flex-wrap items-center gap-3">
                <a data-page-previous hidden class="dc-action-secondary">Anterior</a>
                <span data-page-label class="text-sm text-muted"></span>
                <a data-page-next hidden class="dc-action-secondary">Siguiente</a>
            </nav>
        </div>

        <template data-closet-item>
            <li class="overflow-hidden rounded-card border border-line bg-white">
                <div class="relative grid aspect-[4/3] place-items-center border-b border-line bg-[#eeece6]">
                    <img data-item-photo hidden loading="lazy" alt="" class="absolute inset-0 size-full object-contain">
                    <span data-item-placeholder class="rounded-full border border-line bg-white/75 px-3 py-1 text-xs font-medium text-muted">Sin foto</span>
                </div>
                <div class="grid gap-1.5 p-5">
                    <p data-item-category class="text-xs font-bold tracking-wider text-muted uppercase"></p>
                    <h2 data-item-name class="wrap-anywhere font-heading text-2xl font-semibold"></h2>
                    <p data-item-color hidden class="wrap-anywhere text-sm text-muted"></p>
                    <label class="mt-2 grid w-fit gap-1 text-sm font-semibold">
                        Estilo
                        <select data-item-style class="min-h-10 rounded-lg border border-muted bg-white px-2 py-1 text-sm font-normal text-ink">
                            <option value="">Cualquier estilo</option>
                            @foreach (\App\Models\ClothingItem::STYLES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p data-style-status role="status" class="text-xs text-muted empty:hidden"></p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                        <a data-item-edit class="dc-action-secondary">Editar</a>
                        <button data-item-delete type="button" class="min-h-11 rounded-lg px-3 text-sm font-bold text-red-800 hover:bg-red-50">Eliminar</button>
                    </div>
                </div>
            </li>
        </template>

        <dialog data-delete-dialog aria-labelledby="delete-garment-title" aria-describedby="delete-garment-note"
            class="m-auto w-[min(90vw,26rem)] rounded-panel border border-line bg-white p-6 text-ink shadow-xl backdrop:bg-ink/40">
            <form method="dialog" class="grid gap-4">
                <h2 id="delete-garment-title" class="font-heading text-3xl font-semibold">Eliminar prenda</h2>
                <p id="delete-garment-note" class="wrap-anywhere text-muted">Se eliminará <strong data-delete-name></strong> de tu clóset junto con su foto.</p>
                <p data-delete-status role="status" class="text-sm text-red-800 empty:hidden"></p>
                <div class="flex flex-wrap gap-2">
                    <button data-delete-cancel type="submit" class="dc-action-secondary disabled:opacity-60" autofocus>Cancelar</button>
                    <button data-delete-confirm type="button" class="min-h-11 rounded-xl bg-red-800 px-4 py-2 text-sm font-bold text-white hover:bg-red-900 disabled:opacity-60">Eliminar prenda</button>
                </div>
            </form>
        </dialog>
    </div>
@endsection
