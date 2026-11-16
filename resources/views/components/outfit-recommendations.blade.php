@props(['period'])

<section aria-labelledby="outfits-heading" data-outfits data-period="{{ $period }}"
    data-category-labels="{{ json_encode(\App\Models\ClothingItem::CATEGORIES, JSON_THROW_ON_ERROR) }}"
    class="grid gap-5 rounded-panel border border-line bg-white p-5 sm:p-7">
    <div class="grid gap-2">
        <h2 id="outfits-heading" class="font-heading text-4xl font-semibold">{{ $period === 'today' ? 'Tu outfit de hoy' : 'Outfits para tu semana' }}</h2>
        <p class="text-sm text-muted">Combina tus prendas según el pronóstico de tu ubicación.</p>
    </div>

    <form data-outfit-form method="POST" action="{{ route('outfits.generate', absolute: false) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <x-form-field name="style" label="Estilo" :options="['' => 'Sin preferencia', ...\App\Models\ClothingItem::STYLES]" />
        <button type="submit" class="dc-action-primary disabled:opacity-60">{{ $period === 'today' ? 'Aplicar estilo' : 'Generar outfits' }}</button>
    </form>

    <div class="flex flex-wrap items-center gap-3 empty:hidden">
        <p data-outfit-status role="status" class="text-sm text-muted empty:hidden"></p>
        <a data-outfit-location hidden href="{{ route('account.show') }}#ubicacion" class="text-sm font-bold underline underline-offset-4">Configurar ubicación</a>
        <a data-outfit-closet hidden href="{{ route('closet.create') }}" class="text-sm font-bold underline underline-offset-4">Agregar prendas</a>
    </div>

    <div data-outfit-list class="grid gap-4 empty:hidden {{ $period === 'week' ? 'md:grid-cols-2' : '' }}"></div>

    <template data-outfit-day>
        <article class="grid min-w-0 content-start gap-4 rounded-card border border-line bg-canvas p-4 sm:p-5">
            <header class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-heading text-2xl font-semibold"><time data-outfit-date></time></h3>
                <p data-outfit-weather class="text-sm text-muted"></p>
            </header>

            <div data-outfit-composition class="grid min-w-0 gap-3">
                <figure data-outfit-image hidden class="mx-auto grid w-full max-w-lg gap-3">
                    <canvas width="900" height="1125" class="h-auto w-full rounded-xl bg-[#e2ded5]" role="img"></canvas>
                    <figcaption class="flex justify-end">
                        <button data-download-outfit type="button" class="dc-action-secondary bg-white">Descargar PNG</button>
                    </figcaption>
                </figure>
                <p data-compose-status role="status" class="text-sm text-muted empty:hidden"></p>
                <details data-compose-problems hidden>
                    <summary class="w-fit cursor-pointer rounded px-1 py-2 text-sm font-semibold">Fotos por preparar</summary>
                    <ul data-compose-links class="mt-1 grid gap-2 pl-4 text-sm"></ul>
                </details>
                <button data-compose-outfit type="button" hidden class="dc-action-secondary w-fit bg-white disabled:opacity-60">Volver a crear imagen</button>
            </div>

            <details class="border-t border-line pt-2 text-sm">
                <summary class="w-fit cursor-pointer rounded px-1 py-2 font-semibold">Detalles del outfit</summary>
                <ul data-outfit-garments class="mt-1 flex flex-wrap gap-x-4 gap-y-1 pl-1 font-medium"></ul>
                <p data-outfit-reason class="mt-2 pl-1 text-muted"></p>
                <p data-outfit-missing hidden class="mt-2 pl-1 text-muted"></p>
            </details>
        </article>
    </template>
</section>
