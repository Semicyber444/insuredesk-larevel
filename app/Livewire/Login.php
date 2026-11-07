<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('เข้าสู่ระบบ')]
class Login extends Component
{
    public const INVALID = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่ได้รับเชิญเข้าสู่ระบบ';

    public const MAX_ATTEMPTS = 10;

    public const WINDOW_MINUTES = 15;

    public string $email = '';

    public string $password = '';

    public string $error = '';

    public function login()
    {
        $this->error = '';
        $email = mb_strtolower(trim($this->email));
        // Hashing work grows with input, so cap what a request can make the server hash.
        if ($email === '' || mb_strlen($email) > 255 || $this->password === '' || strlen($this->password) > 1024) {
            return $this->error = self::INVALID;
        }

        $user = User::where('email', $email)->first();

        // Demo accounts sign in with any password while demo auth is on.
        if ($user?->isDemo() && User::demoAuthEnabled()) {
            return $this->signIn($user);
        }

        // Keyed by email rather than IP: X-Forwarded-For can be spoofed unless a trusted proxy sets it.
        // Counted for unknown emails too, so a lockout doesn't reveal which accounts exist.
        $key = 'login:'.hash('sha256', $email);
        RateLimiter::hit($key, self::WINDOW_MINUTES * 60);
        if (RateLimiter::attempts($key) > self::MAX_ATTEMPTS) {
            return $this->error = 'พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ '.self::WINDOW_MINUTES.' นาทีแล้วลองใหม่';
        }

        // Checked even when the email is unknown, so response time doesn't reveal which accounts exist.
        $matches = Hash::check($this->password, $user?->password ?? '$2y$12$'.str_repeat('0', 53));
        if (! $user || ! $user->is_active || ! $user->password || ! $matches) {
            return $this->error = self::INVALID;
        }
        RateLimiter::clear($key);

        return $this->signIn($user);
    }

    public function demoLogin(string $role)
    {
        if (! User::demoAuthEnabled() || ! isset(User::DEMO_USER_IDS[$role])) {
            return $this->error = 'ไม่ได้เปิดใช้งานบัญชีจำลอง';
        }

        return $this->signIn(User::findOrFail(User::DEMO_USER_IDS[$role]));
    }

    private function signIn(User $user)
    {
        Auth::login($user);
        session()->regenerate();

        return $this->redirect('/search');
    }

    public function render()
    {
        return view('livewire.login', ['demoAuth' => User::demoAuthEnabled()]);
    }
}
