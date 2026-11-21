<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\EmailChangeVerificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, EmailChangeVerificationService $emailChange): RedirectResponse
    {
        $user = $request->user();
        $newEmail = mb_strtolower(trim((string) $request->validated('email')));
        $emailChanging = $newEmail !== mb_strtolower((string) $user->email);

        if ($emailChanging) {
            try {
                $emailChange->start($request, $user, $newEmail);
            } catch (DomainException $e) {
                return back()->withInput()->withErrors(['email' => $e->getMessage()]);
            } catch (Throwable $e) {
                report($e);

                return back()->withInput()
                    ->withErrors(['email' => 'We could not send the confirmation code right now. Please try again in a moment.']);
            }
        }

        $user->fill($request->safe()->except('current_password', 'email'));

        if ($request->has('email_invoice')) {
            $user->email_invoice = $request->boolean('email_invoice');
        }

        $user->save();

        if ($emailChanging) {
            return Redirect::route('profile.email.verify');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Deactivate the user's account (never delete, so order history stays intact).
     * deactivated_by = own id marks a self-deactivation, which the member may undo at the login door.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->forceFill([
            'is_active' => false,
            'deactivated_by' => $user->id,
            'modified_by' => $user->id,
        ])->save();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
