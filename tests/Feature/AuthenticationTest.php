<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests can open registration and login', function () {
    $this->get('/register')->assertOk()->assertSee('Crear una cuenta');
    $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
});

test('registers a user with a hashed password and starts a new session', function () {
    $this->withSession(['test_marker' => 'before-registration']);
    $previousSessionId = session()->getId();

    $this->post('/register', [
        'name' => 'Ana',
        'email' => ' ANA@example.com ',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
        'latitude' => 42,
    ])->assertRedirectToRoute('account.show');

    $user = User::where('email', 'ana@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect(Hash::check('a-secure-password', $user->password))->toBeTrue();
    expect(session()->getId())->not->toBe($previousSessionId);
    expect($user->latitude)->toBeNull();
});

test('rejects invalid registration without creating a user', function (array $attributes, array $fields) {
    $this->postJson('/register', $attributes)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);

    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
})->with([
    'missing fields' => [[], ['name', 'email', 'password']],
    'invalid email and short password' => [
        ['name' => 'Ana', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'short'],
        ['email', 'password'],
    ],
    'password confirmation differs' => [
        ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'long-password', 'password_confirmation' => 'different'],
        ['password'],
    ],
]);

test('returns 422 for a duplicate email regardless of casing', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/register', [
        'name' => 'Another Ana', 'email' => 'ANA@example.com',
        'password' => 'long-password', 'password_confirmation' => 'long-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('users', 1);
    $this->assertGuest();
});

test('logs in an existing user and regenerates the session', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $this->withSession(['test_marker' => 'before-login']);
    $previousSessionId = session()->getId();

    $this->post('/login', ['email' => 'ANA@example.com', 'password' => 'password'])
        ->assertRedirectToRoute('account.show');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSessionId);
    $this->get('/account')->assertOk()->assertSee($user->name);
});

test('returns 422 for invalid login without authenticating', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');

    $this->assertGuest();
});

test('returns 429 after five login attempts for the same account and address', function () {
    $credentials = ['email' => 'ana@example.com', 'password' => 'wrong-password'];

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/login', $credentials)->assertUnprocessable();
    }

    $this->postJson('/login', $credentials)->assertTooManyRequests();
    $this->assertGuest();
});

test('logout invalidates the session and removes private access', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['private_marker' => 'secret']);
    $previousToken = session()->token();

    $this->post('/logout')->assertRedirectToRoute('login')->assertSessionMissing('private_marker');

    $this->assertGuest();
    expect(session()->token())->not->toBe($previousToken);
    $this->getJson('/api/v1/me/clothing')->assertUnauthorized();
});

test('guests are redirected to login for the account page', function () {
    $this->get('/account')->assertRedirectToRoute('login');
});

test('authenticated users are redirected away from login and registration', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/login')->assertRedirectToRoute('account.show');
    $this->get('/register')->assertRedirectToRoute('account.show');
});

test('account output escapes the user name', function () {
    $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($user)->get('/account')
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('login rejects a request without csrf protection', function () {
    $this->app['env'] = 'local';

    $this->post('/login', ['email' => 'ana@example.com', 'password' => 'password'])
        ->assertStatus(419);

    $this->assertGuest();
});

test('a login cookie authenticates later api requests with csrf protected writes', function () {
    User::factory()->create(['email' => 'ana@example.com']);
    $this->app['env'] = 'local';
    $this->withSession(['_token' => 'test-csrf-token']);

    $response = $this->post('/login', [
        '_token' => 'test-csrf-token', 'email' => 'ana@example.com', 'password' => 'password',
    ])->assertRedirectToRoute('account.show');

    $response->assertCookie(config('session.cookie'));
    $sessionCookie = $response->getCookie(config('session.cookie'));
    Auth::forgetGuards();

    $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
        ->withHeader('X-CSRF-TOKEN', session()->token())
        ->putJson('/api/v1/me/location', ['name' => 'Santiago', 'latitude' => -33.3, 'longitude' => -70.6])
        ->assertOk()->assertJsonPath('data.name', 'Santiago');

    $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'location_name' => 'Santiago']);
});
