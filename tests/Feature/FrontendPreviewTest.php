<?php

use App\Models\User;

dataset('product sections', [
    'home' => ['/inicio', 'Inicio', 'home'],
    'week' => ['/mi-semana', 'Mi semana', 'week.index'],
    'closet' => ['/mi-closet', 'Mi clóset', 'closet.index'],
]);

test('guests must sign in before opening a product section', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');
})->with('product sections');

test('members see the requested product section', function (string $path, string $title, string $view) {
    $this->withoutVite();

    $this->actingAs(User::factory()->make())
        ->get($path)
        ->assertOk()
        ->assertViewIs($view)
        ->assertSeeText($title.' · DreamCloset');
})->with('product sections');
