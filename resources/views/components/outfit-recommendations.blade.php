@props(['period'])

<section aria-labelledby="outfits-heading" data-outfits data-period="{{ $period }}" class="grid gap-5 rounded-panel border border-line bg-white p-5 sm:p-7">
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
        <article class="group grid min-w-0 content-start gap-4 rounded-card border border-line bg-canvas p-4 {{ $period === 'today' ? 'md:data-[composed=true]:grid-cols-2' : '' }}">
            <div class="grid min-w-0 content-start gap-3 {{ $period === 'today' ? 'md:group-data-[composed=true]:order-2' : '' }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-heading text-2xl font-semibold"><time data-outfit-date></time></h3>
                    <p data-outfit-weather class="text-sm text-muted"></p>
                </div>
                <ul data-outfit-garments class="grid grid-cols-2 gap-2"></ul>
                <p data-outfit-reason class="text-sm text-muted"></p>
                <p data-outfit-missing hidden class="text-sm font-semibold"></p>
            </div>
            <div data-outfit-composition class="grid min-w-0 content-start gap-3">
                <button data-compose-outfit type="button" hidden class="dc-action-secondary w-fit bg-white disabled:opacity-60">Crear imagen del outfit</button>
                <figure data-outfit-image hidden class="grid max-w-xl gap-3">
                    <canvas class="aspect-[4/5] w-full rounded-xl bg-[#e5d8c5]" role="img"></canvas>
                    <button data-download-outfit type="button" class="dc-action-secondary w-fit bg-white">Descargar imagen (PNG)</button>
                </figure>
                <p data-compose-status role="status" class="text-sm text-muted empty:hidden"></p>
                <ul data-compose-problems hidden class="grid gap-2 text-sm"></ul>
            </div>
        </article>
    </template>

    <template data-outfit-garment>
        <li class="flex min-w-0 items-center gap-2 rounded-xl border border-line bg-white p-2">
            <img data-outfit-photo hidden alt="" loading="lazy" class="size-10 shrink-0 rounded-lg bg-[#eeece6] object-contain">
            <div class="min-w-0">
                <p data-outfit-category class="text-xs text-muted"></p>
                <p data-outfit-name class="wrap-anywhere text-sm font-semibold"></p>
            </div>
        </li>
    </template>
</section>
