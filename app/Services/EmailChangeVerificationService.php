<?php

namespace App\Services;

use App\Mail\EmailChangeCodeMail;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class EmailChangeVerificationService
{
    private const SESSION_KEY = 'email_change_pending';

    /** Seconds a code stays valid. */
    public const CODE_TTL = 600;

    /** Seconds the waiting change stays in the session, counted from the last code that was sent. */
    private const PENDING_TTL = 1800;

    /** Wrong codes allowed before the change has to start again. */
    public const MAX_ATTEMPTS = 5;

    /** Seconds before another code can be requested. */
    public const RESEND_COOLDOWN = 60;

    /** Changes that may be started per member per hour, so nobody can flood an inbox. */
    private const MAX_STARTS_PER_HOUR = 5;

    /** Mails the first code. Throws when it cannot be sent, then nothing is stored. */
    public function start(Request $request, User $user, string $email): void
    {
        $limitKey = 'email-change|'.$user->id;

        if (RateLimiter::tooManyAttempts($limitKey, self::MAX_STARTS_PER_HOUR)) {
            throw new DomainException('Too many email change requests. Please try again later.');
        }

        $code = $this->newCode();

        $this->deliver($user, $email, $code);

        RateLimiter::hit($limitKey, 3600);

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'email' => $email,
            'code' => $this->hashCode($code, $email),
            'expires_at' => now()->timestamp + self::CODE_TTL,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
        ]);
    }

    /** The change waiting for its code, or null (also when it belongs to someone else or timed out). */
    public function pending(Request $request, User $user): ?array
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data)
            || (int) ($data['user_id'] ?? 0) !== $user->id
            || (now()->timestamp - (int) ($data['sent_at'] ?? 0)) > self::PENDING_TTL) {
            $this->forget($request);

            return null;
        }

        return $data;
    }

    /** Seconds left before a new code may be requested. */
    public function cooldownLeft(array $pending): int
    {
        return max(0, self::RESEND_COOLDOWN - (now()->timestamp - (int) $pending['sent_at']));
    }

    /** Sends a new code; the old one stops working. */
    public function resend(Request $request, User $user): void
    {
        $pending = $this->pending($request, $user);

        if (! $pending) {
            throw new DomainException('This email change has expired. Please start again from your profile.');
        }

        if ($this->cooldownLeft($pending) > 0) {
            throw new DomainException('Please wait a moment before asking for a new code.');
        }

        $code = $this->newCode();

        $this->deliver($user, $pending['email'], $code);

        $pending['code'] = $this->hashCode($code, $pending['email']);
        $pending['expires_at'] = now()->timestamp + self::CODE_TTL;
        $pending['sent_at'] = now()->timestamp;
        $pending['attempts'] = 0;

        $request->session()->put(self::SESSION_KEY, $pending);
    }

    /** Checks the code and switches the member to the new, verified email. */
    public function verify(Request $request, User $user, string $code): void
    {
        $pending = $this->pending($request, $user);

        if (! $pending) {
            throw new DomainException('This email change has expired. Please start again from your profile.');
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            throw new DomainException('This code has expired. Ask for a new code.');
        }

        if (! hash_equals($pending['code'], $this->hashCode($code, $pending['email']))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $this->forget($request);

                throw new DomainException('Too many wrong codes. Please start again from your profile.');
            }

            $request->session()->put(self::SESSION_KEY, $pending);

            throw new DomainException('That code is not correct. Please check your email and try again.');
        }

        if (User::where('email', $pending['email'])->where('id', '!=', $user->id)->exists()) {
            $this->forget($request);

            throw new DomainException('This email is already registered. Please use another email.');
        }

        $user->forceFill([
            'email' => $pending['email'],
            'email_verified_at' => now(),
        ])->save();

        $this->forget($request);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function deliver(User $user, string $email, string $code): void
    {
        Mail::to($email)->send(new EmailChangeCodeMail($user->name, $code, intdiv(self::CODE_TTL, 60)));
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $code, string $email): string
    {
        return hash_hmac('sha256', $code.'|'.mb_strtolower($email), (string) config('app.key'));
    }
}
