@php
    $hasIngredients = $drink['ingredients'] !== [];
    $ingredientColumns = array_chunk($drink['ingredients'], max(1, (int) ceil(count($drink['ingredients']) / 2)));
@endphp

    <div class="mt-5 rounded-card p-4 sm:p-6" style="background-color: {{ $drink['color'] }}">
        @if ($hasIngredients)
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-bold">Ingredientes</h3>
                <p class="text-sm text-ink/75">Para 1 vaso</p>
            </div>
        @endif

        <div @class(['grid items-center gap-3 sm:gap-4', 'grid-cols-2 md:grid-cols-[1fr_1.4fr_1fr]' => $hasIngredients])>
            <figure @class(['mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm', 'col-span-2 md:col-span-1 md:col-start-2 md:row-start-1' => $hasIngredients])>
                <img src="{{ asset('images/drinks/'.$drink['slug'].'.jpg') }}" alt="{{ $drink['name'] }}"
                    class="aspect-[4/5] w-full object-cover" width="480" height="600" decoding="async" loading="lazy">
            </figure>

            @foreach ($ingredientColumns as $column)
                <ul @class(['grid gap-2.5 md:row-start-1', 'md:col-start-1' => $loop->first, 'md:col-start-3' => $loop->last])>
                    @foreach ($column as [$quantity, $name])
                        <li class="grid gap-1 rounded-xl border border-ink/10 bg-white/70 px-3 py-2.5 sm:px-4">
                            <span class="text-xs font-semibold tabular-nums text-ink/75">{{ $quantity }}</span>
                            <span class="text-sm leading-snug font-bold">{{ $name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>

    <div class="mt-5 grid gap-2">
        <h3 class="font-heading text-2xl font-semibold">{{ $hasIngredients ? 'Preparación' : 'Cómo servir' }}</h3>
        <ol @class(['grid gap-1.5 text-sm leading-relaxed text-muted', 'list-decimal pl-5' => count($drink['steps']) > 1])>
            @foreach ($drink['steps'] as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
        <p class="mt-2 text-xs text-muted">
            Foto de <a href="{{ $drink['photo']['source'] }}" class="underline underline-offset-2">{{ $drink['photo']['author'] }}</a> ·
            @if ($drink['photo']['license_url'])
                <a href="{{ $drink['photo']['license_url'] }}" class="underline underline-offset-2">{{ $drink['photo']['license'] }}</a>
            @else
                {{ $drink['photo']['license'] }}
            @endif
            · Tamaño adaptado
        </p>
    </div>
