<?php

use App\DailyDrink;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

test('only the signed in account can opt in and its drink card appears below the outfit', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-11-16 03:05:00', 'UTC'));
    $member = User::factory()->create();
    $otherMember = User::factory()->create();

    $this->patch(route('account.easter-eggs'), ['easter_eggs' => '1'])->assertRedirectToRoute('login');

    $this->actingAs($member)->get(route('account.show'))->assertOk()->assertSee('aria-checked="false"', false);
    $this->get(route('home'))->assertOk()->assertDontSeeText('Te recomiendo este trago para hoy');

    $this->patchJson(route('account.easter-eggs'), ['easter_eggs' => 'tal vez'])
        ->assertUnprocessable()->assertJsonValidationErrors('easter_eggs');

    $this->patch(route('account.easter-eggs'), ['easter_eggs' => '1', 'user_id' => $otherMember->id])
        ->assertRedirect(route('account.show').'#easter-eggs');

    expect($member->refresh()->easter_eggs)->toBeTrue()
        ->and($otherMember->refresh()->easter_eggs)->toBeFalse();

    $drink = DailyDrink::today();

    $this->get(route('home'))->assertOk()->assertSeeTextInOrder([
        'Tu outfit de hoy', 'Te recomiendo este trago para hoy', $drink['name'],
    ])->assertSee(asset('images/drinks/'.$drink['slug'].'.jpg'), false);

    $this->patch(route('account.easter-eggs'), ['easter_eggs' => '0'])->assertRedirect();
    $this->get(route('home'))->assertOk()->assertDontSeeText('Te recomiendo este trago para hoy');
});

test('the drink rotates only at midnight in Chile and covers the 20 photographed drinks', function () {
    $start = Carbon::parse('2026-11-16 02:55:00', 'UTC');
    $this->travelTo($start);
    $beforeMidnight = DailyDrink::today()['slug'];
    $this->travelTo($start->copy()->addMinutes(4));
    expect(DailyDrink::today()['slug'])->toBe($beforeMidnight);

    $firstFullDay = $start->copy()->addMinutes(10);
    $seen = [];
    for ($offset = 0; $offset < 20; $offset++) {
        $this->travelTo($firstFullDay->copy()->addDays($offset));
        $drink = DailyDrink::today();
        expect(public_path('images/drinks/'.$drink['slug'].'.jpg'))->toBeFile();
        $seen[] = $drink['slug'];
    }

    expect($seen[0])->not->toBe($beforeMidnight)
        ->and(array_unique($seen))->toHaveCount(20);
    $this->travelTo($firstFullDay->copy()->addDays(20));
    expect(DailyDrink::today()['slug'])->toBe($seen[0]);
});

test('another drink remains selected today and resets on the next day', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-11-16 03:05:00', 'UTC'));
    $route = route('daily-drink.another');
    $member = User::factory()->create();

    $this->post($route)->assertRedirectToRoute('login');
    $this->actingAs($member)->post($route)->assertForbidden();
    $member->easter_eggs = true;
    $member->save();

    $this->post($route)->assertRedirect(route('home').'#trago-del-dia');
    $this->get(route('home'))->assertOk()->assertViewHas('drink', DailyDrink::today(1));
    $this->post($route)->assertRedirect();
    $this->get(route('home'))->assertOk()->assertViewHas('drink', DailyDrink::today(2));

    $this->travel(1)->day();
    $this->get(route('home'))->assertOk()->assertViewHas('drink', DailyDrink::today());
});

test('the next drink can be fetched without navigating away from the current page', function () {
    $user = User::factory()->create(['easter_eggs' => true]);
    $this->actingAs($user);
    $this->travelTo(Carbon::parse('2026-11-16 03:05:00', 'UTC'));

    $this->postJson(route('daily-drink.another'))->assertOk()
        ->assertJsonPath('name', DailyDrink::today(1)['name'])
        ->assertJsonPath('recipe', fn (string $html): bool => str_contains($html, DailyDrink::today(1)['slug'].'.jpg'));
});
