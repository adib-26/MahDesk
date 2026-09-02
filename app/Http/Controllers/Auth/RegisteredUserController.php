<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\MemberRole;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // A self-registering customer may already exist as a contact in one
        // or more organizations. Link only previously unclaimed contacts and
        // create the least-privileged workspace membership for each.
        Contact::query()
            ->whereNull('user_id')
            ->whereRaw('lower(email) = ?', [strtolower($user->email)])
            ->with('workspace')
            ->get()
            ->each(function (Contact $contact) use ($user) {
                $contact->update(['user_id' => $user->id]);

                if (! $contact->workspace->hasMember($user)) {
                    $contact->workspace->members()->attach($user->id, [
                        'role' => MemberRole::Customer->value,
                    ]);
                }
            });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('verification.notice');
    }
}
