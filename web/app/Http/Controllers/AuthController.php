<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string']);
        $user = User::where('email', $data['login'])->orWhere('emp_code', $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withInput()->withErrors(['login' => 'Wrong email / employee code or password.']);
        }
        if ($user->status !== 'active') {
            return back()->withInput()->withErrors(['login' => 'This account is inactive.']);
        }
        if ($user->role === 'employee') {
            return back()->withInput()->withErrors(['login' => 'Employees use the mobile app. The web is for Admin, Manager and Account Manager.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function password()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'new_password' => 'required|string|min:6|confirmed'],
            ['new_password.confirmed' => 'The two new passwords do not match.']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is wrong.']);
        }
        $request->user()->update(['password' => $data['new_password']]);

        return back()->with('ok', 'Password changed.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
