<?php

use App\Models\User;

test('the root takes guests to login and remembers home', function () {
    $this->withoutVite();

    $this->followingRedirects()->get('/')
        ->assertOk()
        ->assertViewIs('auth.login')
        ->assertSessionHas('url.intended', route('home'));
});

test('the root takes signed-in users to home', function () {
    $this->actingAs(User::factory()->make())
        ->get('/')
        ->assertRedirectToRoute('home');
});
