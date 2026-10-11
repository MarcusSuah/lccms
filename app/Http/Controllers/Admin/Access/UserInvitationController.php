<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

/**
 * Re-sends the password set-up link to a staff member.
 */
class UserInvitationController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A new set-up link was sent to :email.', ['email' => $user->email])]);

        return back();
    }
}
