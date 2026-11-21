<?php

namespace App\Http\Controllers;

use App\Services\EmailChangeVerificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class EmailChangeController extends Controller
{
    public function show(Request $request, EmailChangeVerificationService $emailChange): View|RedirectResponse
    {
        $pending = $emailChange->pending($request, $request->user());

        if (! $pending) {
            return redirect()->route('profile.edit');
        }

        return view('profile.verify-email-change', [
            'newEmail' => $pending['email'],
            'cooldown' => $emailChange->cooldownLeft($pending),
            'minutes' => intdiv(EmailChangeVerificationService::CODE_TTL, 60),
        ]);
    }

    public function store(Request $request, EmailChangeVerificationService $emailChange): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        try {
            $emailChange->verify($request, $request->user(), (string) $request->input('code'));
        } catch (DomainException $e) {

            if (! $emailChange->pending($request, $request->user())) {
                return redirect()->route('profile.edit')->withErrors(['email' => $e->getMessage()]);
            }

            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('profile.edit')->with('status', 'email-updated');
    }

    public function resend(Request $request, EmailChangeVerificationService $emailChange): RedirectResponse
    {
        try {
            $emailChange->resend($request, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['code' => 'We could not send the code right now. Please try again in a moment.']);
        }

        return back()->with('status', 'A new code has been sent to your new email.');
    }

    public function cancel(Request $request, EmailChangeVerificationService $emailChange): RedirectResponse
    {
        $emailChange->forget($request);

        return redirect()->route('profile.edit');
    }
}
