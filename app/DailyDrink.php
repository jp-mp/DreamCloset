<?php

namespace App;

use DateTimeImmutable;
use DateTimeZone;

class DailyDrink
{
    public static function date(): string
    {
        return now('America/Santiago')->toDateString();
    }

    public static function today(int $offset = 0): array
    {
        $calendarDay = new DateTimeImmutable(self::date(), new DateTimeZone('UTC'));
        $dayNumber = intdiv($calendarDay->getTimestamp(), 86400);
        $catalog = config('daily-drinks');
        $keys = array_keys($catalog);
        $index = (($dayNumber + $offset) % count($keys) + count($keys)) % count($keys);
        $key = $keys[$index];

        return ['slug' => $key, ...$catalog[$key]];
    }
}
