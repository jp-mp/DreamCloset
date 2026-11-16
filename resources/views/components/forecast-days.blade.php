<ul data-forecast-list hidden aria-label="Pronóstico diario" class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-7"></ul>

<template data-forecast-day>
    <li class="grid min-w-0 gap-4 rounded-card border border-line bg-white px-4 py-5 sm:gap-6">
        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
            <span data-day class="text-sm font-bold"></span>
            <time data-date class="text-xs text-muted"></time>
        </div>
        <p class="flex items-center gap-2 text-sm text-muted">
            <span data-icon class="text-[1.75rem] leading-none text-ink" aria-hidden="true"></span>
            <span data-condition class="sr-only"></span>
        </p>
        <div class="flex flex-wrap items-baseline gap-2">
            <span class="font-heading text-2xl font-semibold"><span class="sr-only">Máxima </span><span data-high></span></span>
            <span class="text-sm text-muted"><span class="sr-only">Mínima </span><span data-low></span></span>
        </div>
        <p class="text-xs text-muted">Lluvia <span data-rain></span></p>
    </li>
</template>
