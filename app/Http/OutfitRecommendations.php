<?php

namespace App\Http;

use App\Models\ClothingItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OutfitRecommendations
{
    public function __construct(private GroqOutfits $groq) {}

    /** @param  Collection<int, ClothingItem>  $items */
    public function generate(int $userId, float $latitude, string $period, ?string $style, array $forecast, Collection $items, bool $automatic = false, array $previous = [], array $recent = []): ?array
    {
        $weatherDays = $this->weatherDays($forecast, $period);

        if ($weatherDays === []) {
            return null;
        }

        $days = collect($weatherDays)->mapWithKeys(fn (array $weather): array => [
            $weather['date'] => [...$weather, ...$this->candidates($items, $weather, $latitude, $style)],
        ])->all();
        if ($previous !== []) {
            return $this->result($days, [], $previous, $recent);
        }

        $wardrobe = $items->keyBy('id');
        $garments = collect($days)->pluck('candidates')->flatten()->unique()
            ->map(fn (int $id): array => $wardrobe[$id]->only(['id', 'category', 'name', 'color', 'warmth', 'waterproof', 'style']))
            ->values()->all();
        $key = 'outfits:v3:'.$userId.':'.hash('sha256', serialize([$period, $style, $days, $garments, config('services.groq.model'), filled(config('services.groq.key'))]));

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        if ($automatic) {
            return $this->result($days, []);
        }

        $lock = Cache::lock($key.':lock', 20);

        if (! $lock->get()) {
            return $this->result($days, []);
        }

        try {
            $cached = Cache::get($key);

            if ($cached !== null) {
                return $cached;
            }

            $suggestions = $this->groq->suggest(array_values(array_map(fn (array $day): array => array_diff_key($day, ['alternatives' => true]), $days)), $garments, $style);
            $approved = $this->validateSuggestions($suggestions, $days);
            $result = $this->result($days, $approved);
            Cache::put($key, $result, now()->addMinutes($approved === [] ? 3 : 20));

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function weatherDays(array $forecast, string $period): array
    {
        $current = $forecast['current'];
        $daily = $forecast['daily'];
        $dates = $daily['time'] ?? [];
        if (! is_array($dates)) {
            return [];
        }

        $dates = array_slice($dates, 0, $period === 'today' ? 1 : 7);
        $weatherDays = [];

        foreach ($dates as $index => $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
                continue;
            }

            $low = $daily['apparent_temperature_min'][$index] ?? $daily['temperature_2m_min'][$index] ?? null;
            $high = $daily['apparent_temperature_max'][$index] ?? $daily['temperature_2m_max'][$index] ?? null;
            $temperature = $period === 'today'
                ? ($current['apparent_temperature'] ?? $current['temperature_2m'] ?? null)
                : (is_numeric($low) && is_numeric($high) ? ($low + $high) / 2 : null);

            if (! is_numeric($temperature)) {
                continue;
            }

            $rain = ($daily['precipitation_probability_max'][$index] ?? 0) >= 40
                || ($daily['weather_code'][$index] ?? 0) >= 51
                || ($period === 'today' && (($current['precipitation'] ?? 0) > 0 || ($current['weather_code'] ?? 0) >= 51));
            $wind = $period === 'today' ? ($current['wind_speed_10m'] ?? 0) : ($daily['wind_speed_10m_max'][$index] ?? 0);
            $weatherDays[] = [
                'date' => $date,
                'temperature' => round((float) $temperature),
                'min' => is_numeric($low) ? round((float) $low) : null,
                'max' => is_numeric($high) ? round((float) $high) : null,
                'rain' => $rain,
                'windy' => $wind >= 30,
            ];
        }

        return $weatherDays;
    }

    /** @param  Collection<int, ClothingItem>  $items */
    private function candidates(Collection $items, array $day, float $latitude, ?string $style): array
    {
        $warmth = $day['temperature'] < 12 ? 2 : ($day['temperature'] < 21 ? 1 : 0);
        $needsLayer = $day['temperature'] < 18 || ($day['min'] ?? 18) < 13 || $day['rain'] || $day['windy'];
        $season = $this->season($day['date'], $latitude);
        $rank = ['light' => 0, 'medium' => 1, 'warm' => 2];
        $candidates = [];
        $alternatives = [];

        $onlyOuterwear = $items->every(fn (ClothingItem $item): bool => $item->category === 'outerwear');

        foreach (array_keys(ClothingItem::CATEGORIES) as $category) {
            $options = $items->where('category', $category);

            if ($options->isEmpty() || ($category === 'outerwear' && ! $needsLayer && ! $onlyOuterwear)) {
                continue;
            }

            $waterproof = $options->where('waterproof', true);
            if ($day['rain'] && in_array($category, ['outerwear', 'footwear'], true) && $waterproof->isNotEmpty()) {
                $options = $waterproof;
            }

            $score = function (ClothingItem $item) use ($rank, $warmth, $season, $style): int {
                $warmthPenalty = abs($rank[$item->warmth] - $warmth) * 4;
                $seasonPenalty = in_array($item->season, ['all', $season], true) ? 0 : 1;
                $stylePenalty = match (true) {
                    $style === null, $item->style === $style => 0,
                    $item->style === null => 2,
                    default => 3,
                };

                return $warmthPenalty + $seasonPenalty + $stylePenalty;
            };
            $ranked = $options->sortBy($score);
            $best = $ranked->first();
            $eligible = $ranked->filter(fn (ClothingItem $item): bool => $score($item) <= $score($best) + 4
                && ($style === null || $best->style !== $style || in_array($item->style, [$style, null], true)));

            if ($category === 'accessory') {
                $slots = $this->accessorySlots($eligible);
                foreach ($slots as $slot => $options) {
                    $candidates[$slot] = array_slice($options, 0, 3);
                    $alternatives[$slot] = $options;
                }

                continue;
            }

            $candidates[$category] = $ranked->take(3)->pluck('id')->values()->all();
            $alternatives[$category] = $eligible->take(4)->pluck('id')->values()->all();
        }

        return ['candidates' => $candidates, 'alternatives' => $alternatives];
    }

    /**
     * @param  Collection<int, ClothingItem>  $items
     * @return array{accessory: list<int>, accessory_extra?: list<int>}
     */
    private function accessorySlots(Collection $items): array
    {
        $ranked = $items->map(fn (ClothingItem $item): array => [
            'id' => $item->id,
            'type' => $this->accessoryType($item),
        ]);
        $primaryType = $ranked->first()['type'];
        $primary = $ranked->whereStrict('type', $primaryType)->take(4)->pluck('id')->all();
        $additional = $ranked->filter(fn (array $item): bool => $item['type'] !== null && $item['type'] !== $primaryType)
            ->take(4)->pluck('id')->all();

        $slots = ['accessory' => $primary];
        if ($primaryType !== null && $additional !== []) {
            $slots['accessory_extra'] = $additional;
        }

        return $slots;
    }

    private function accessoryType(ClothingItem $item): ?string
    {
        $name = Str::lower(Str::ascii($item->name));

        return match (true) {
            (bool) preg_match('/\b(reloj(?:es)?|smart[ -]?watch(?:es)?|watches|watch)\b/', $name) => 'watch',
            (bool) preg_match('/\b(gorras?|gorros?|sombreros?|boinas?|viseras?|jockeys?|buckets?)\b/', $name) => 'headwear',
            (bool) preg_match('/\b(gafas?|lentes?|anteojos?)\b/', $name) => 'eyewear',
            (bool) preg_match('/\b(mochilas?|bolsos?|bandoleras?|bananos?|rinoneras?|carteras?)\b/', $name) => 'bag',
            (bool) preg_match('/\b(cinturon(?:es)?|cintos?)\b/', $name) => 'belt',
            (bool) preg_match('/\b(bufandas?|panuelos?|corbatas?|pajaritas?|collar(?:es)?|cadenas?)\b/', $name) => 'neckwear',
            (bool) preg_match('/\b(pulseras?|brazaletes?)\b/', $name) => 'bracelet',
            (bool) preg_match('/\b(anillos?)\b/', $name) => 'ring',
            (bool) preg_match('/\b(aros?|pendientes?|aretes?)\b/', $name) => 'earrings',
            (bool) preg_match('/\b(guantes?)\b/', $name) => 'gloves',
            default => null,
        };
    }

    private function season(string $date, float $latitude): string
    {
        $month = (int) substr($date, 5, 2);
        $quarter = intdiv($month % 12, 3);
        $seasons = ['winter', 'spring', 'summer', 'autumn'];

        return $seasons[($quarter + ($latitude < 0 ? 2 : 0)) % 4];
    }

    private function validateSuggestions(?array $suggestions, array $days): array
    {
        if ($suggestions === null || count($suggestions) !== count($days)) {
            return [];
        }

        $approved = [];

        foreach ($suggestions as $outfit) {
            $date = $outfit['date'] ?? null;
            $ids = $outfit['garment_ids'] ?? null;
            $reason = $outfit['reason'] ?? null;

            if (! is_string($date) || ! isset($days[$date]) || isset($approved[$date])
                || ! is_array($ids) || ! is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 280) {
                return [];
            }

            $categories = $days[$date]['candidates'];

            if (count($ids) !== count($categories)) {
                return [];
            }

            $selected = [];
            foreach ($categories as $options) {
                $matches = array_values(array_filter($ids, fn (mixed $id): bool => is_int($id) && in_array($id, $options, true)));

                if (count($matches) !== 1) {
                    return [];
                }

                $selected[] = $matches[0];
            }

            $approved[$date] = ['garment_ids' => $selected, 'reason' => trim($reason)];
        }

        return $approved;
    }

    private function localOutfit(array $categories, array $previous, array $seen, array $wears, array $recent): array
    {
        $combinations = [[]];

        foreach ($categories as $options) {
            $combinations = array_merge(...array_map(
                fn (array $ids): array => array_map(fn (int $id): array => [...$ids, $id], $options),
                $combinations,
            ));
        }

        $best = [];
        $bestScore = null;

        foreach ($combinations as $ids) {
            $overlap = count(array_intersect($ids, $previous));
            $signature = implode(',', $ids);
            $score = [
                $overlap === count($ids) && $overlap === count($previous) ? 1 : 0,
                isset($seen[$signature]) ? 1 : 0,
                in_array($signature, $recent, true) ? 1 : 0,
                $overlap,
                array_sum(array_map(fn (int $id): int => $wears[$id] ?? 0, $ids)),
            ];

            if ($bestScore === null || $score < $bestScore) {
                $best = $ids;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function result(array $days, array $approved, array $previous = [], array $recent = []): array
    {
        $outfits = [];
        $seen = [];
        $wears = [];
        $modelOutfits = 0;

        foreach ($days as $date => $day) {
            $candidates = $day['candidates'];
            $alternatives = $day['alternatives'];
            unset($day['candidates'], $day['alternatives']);

            $suggestion = $approved[$date] ?? null;
            $ids = $suggestion['garment_ids'] ?? [];
            $recentSignatures = array_map(fn (array $ids): string => implode(',', $ids), $recent[$date] ?? []);
            $local = $this->localOutfit($alternatives, $previous[$date] ?? [], $seen, $wears, $recentSignatures);
            if ($suggestion && (! isset($seen[implode(',', $ids)]) || isset($seen[implode(',', $local)]))) {
                $modelOutfits++;
            } else {
                $suggestion = null;
                $ids = $local;
            }

            $seen[implode(',', $ids)] = true;
            foreach ($ids as $id) {
                $wears[$id] = ($wears[$id] ?? 0) + 1;
            }

            $outfits[] = [
                ...$day,
                'missing' => array_values(array_diff(['top', 'bottom', 'footwear'], array_keys($candidates))),
                ...($suggestion ?? [
                    'garment_ids' => $ids,
                    'reason' => match (true) {
                        $day['rain'] => 'Se espera precipitación. La combinación prioriza las prendas impermeables que tengas disponibles.',
                        $day['temperature'] < 18 || $day['windy'] || ($day['min'] ?? 18) < 13 => 'El frío o el viento pueden pedir una capa extra. La selección aprovecha el abrigo que tienes disponible.',
                        default => 'Para la temperatura prevista, la selección prioriza prendas con un nivel de abrigo adecuado.',
                    },
                ]),
            ];
        }

        $source = match ($modelOutfits) {
            0 => 'rules',
            count($outfits) => 'groq',
            default => 'mixed',
        };

        return ['source' => $source, 'outfits' => $outfits];
    }
}
