<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Твърде много опити. Опитайте след '.RateLimiter::availableIn($key).' секунди.']);
        }
        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password']], true)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'Грешен имейл или парола.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('overview'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
