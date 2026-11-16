@props(['drink'])

<x-panel id="trago-del-dia" data-daily-drink aria-labelledby="daily-drink-heading" class="scroll-mt-28 overflow-hidden">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-bold tracking-widest text-muted uppercase">Te recomiendo este trago para hoy</p>
            <h2 id="daily-drink-heading" aria-live="polite" class="mt-2 font-heading text-4xl font-semibold tracking-tight sm:text-5xl">{{ $drink['name'] }}</h2>
        </div>
        <form method="POST" action="{{ route('daily-drink.another') }}">
            @csrf
            <button type="submit" class="dc-action-secondary aria-disabled:opacity-60">Ver otro trago</button>
        </form>
    </div>

    <p data-daily-drink-error role="status" class="mt-2 text-sm text-red-800 empty:hidden"></p>
    <div data-daily-drink-recipe>
        @include('components.daily-drink-recipe', ['drink' => $drink])
    </div>
</x-panel>
