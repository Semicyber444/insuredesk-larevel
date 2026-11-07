<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** The navbar's role switcher: only a signed-in demo user may hop between demo users. */
    public function switchDemoRole(Request $request, string $role): RedirectResponse
    {
        abort_unless($request->user()->isDemo() && User::demoAuthEnabled()
            && isset(User::DEMO_USER_IDS[$role]), 403, 'สลับบทบาทได้เฉพาะบัญชีจำลอง');

        Auth::loginUsingId(User::DEMO_USER_IDS[$role]);
        $request->session()->regenerate();

        return redirect()->back();
    }
}
