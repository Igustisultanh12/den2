<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'location_mocked' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginInput = trim($this->input('email') ?? $this->input('username') ?? '');

        if (empty($loginInput)) {
            throw ValidationException::withMessages([
                'email' => 'Silakan masukkan NRP, Username, atau Alamat Email Anda.',
            ]);
        }

        $password = $this->input('password');
        $remember = $this->boolean('remember');

        // 1. Cek Login via Email (jika format email valid)
        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            if (Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)) {
                RateLimiter::clear($this->throttleKey());
                return;
            }
        }

        // 2. Cek Login via NRP (Kolom nrp di tabel users)
        if (Schema::hasColumn('users', 'nrp')) {
            if (Auth::attempt(['nrp' => $loginInput, 'password' => $password], $remember)) {
                RateLimiter::clear($this->throttleKey());
                return;
            }
        }

        // 3. Cek Login via Email murni (jika input string email tanpa format standar)
        if (Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        // 4. Cek Login via Username (Hanya jika kolom username ada di tabel users)
        if (Schema::hasColumn('users', 'username')) {
            if (Auth::attempt(['username' => $loginInput, 'password' => $password], $remember)) {
                RateLimiter::clear($this->throttleKey());
                return;
            }
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Kredensial tidak cocok. Silakan periksa NRP / Email dan Kata Sandi Anda.',
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $loginInput = trim($this->input('email') ?? $this->input('username') ?? '');
        return Str::transliterate(Str::lower($loginInput).'|'.$this->ip());
    }
}