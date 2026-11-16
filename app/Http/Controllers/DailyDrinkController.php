<?php

namespace App\Http\Controllers;

use App\DailyDrink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyDrinkController extends Controller
{
    public function showHome(Request $request): View
    {
        $drink = $request->user()->easter_eggs ? DailyDrink::today($this->offset($request)) : null;

        return view('home', ['drink' => $drink]);
    }

    public function another(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->easter_eggs, 403);

        $offset = ($this->offset($request) + 1) % count(config('daily-drinks'));
        $request->session()->put('daily_drink', [
            'date' => DailyDrink::date(),
            'user_id' => $request->user()->getKey(),
            'offset' => $offset,
        ]);

        if ($request->expectsJson()) {
            $drink = DailyDrink::today($offset);

            return response()->json([
                'name' => $drink['name'],
                'recipe' => view('components.daily-drink-recipe', ['drink' => $drink])->render(),
            ]);
        }

        return redirect(route('home').'#trago-del-dia');
    }

    private function offset(Request $request): int
    {
        $selection = $request->session()->get('daily_drink', []);

        if (($selection['date'] ?? null) !== DailyDrink::date()
            || ($selection['user_id'] ?? null) !== $request->user()->getKey()) {
            return 0;
        }

        return $selection['offset'];
    }
}
