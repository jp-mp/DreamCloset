<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($attributes);
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('account.show');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function account(Request $request): View
    {
        return view('account', ['user' => $request->user()]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $attributes = $request->validate(['profile_name' => ['required', 'string', 'max:100']]);

        $request->user()->update(['name' => $attributes['profile_name']]);

        return redirect(route('account.show').'#editar-nombre')
            ->with('profile_name_status', 'Nombre guardado.');
    }

    public function updateEasterEggs(Request $request): RedirectResponse
    {
        $request->validate(['easter_eggs' => ['required', 'boolean']]);

        $user = $request->user();
        $user->easter_eggs = $request->boolean('easter_eggs');
        $user->save();

        return redirect(route('account.show').'#easter-eggs');
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }
    }
}
