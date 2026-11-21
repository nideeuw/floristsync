<?php

namespace App\Services;

use App\Mail\RegistrationCodeMail;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email check while registering: the account is created only after the member enters the code
 * that was mailed to the address. Until then the data waits in the session (the password already hashed).
 */
class RegistrationVerificationService
{
    private const SESSION_KEY = 'registration_pending';

    /** Seconds a code stays valid. */
    public const CODE_TTL = 600;

    /** Seconds the waiting registration stays in the session, counted from the last code that was sent. */
    private const PENDING_TTL = 1800;

    /** Wrong codes allowed before the registration has to start again. */
    public const MAX_ATTEMPTS = 5;

    /** Seconds before another code can be requested. */
    public const RESEND_COOLDOWN = 60;

    /** Mails the first code. Throws when the mail cannot be sent, then nothing is stored. */
    public function start(Request $request, string $name, string $email, string $password): void
    {
        $code = $this->newCode();

        $this->deliver($name, $email, $code);

        $request->session()->put(self::SESSION_KEY, [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'code' => $this->hashCode($code, $email),
            'expires_at' => now()->timestamp + self::CODE_TTL,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
        ]);
    }

    /** The registration waiting for its code, or null (also when it timed out). */
    public function pending(Request $request): ?array
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || (now()->timestamp - (int) ($data['sent_at'] ?? 0)) > self::PENDING_TTL) {
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
    public function resend(Request $request): void
    {
        $pending = $this->pending($request);

        if (! $pending) {
            throw new DomainException('Your registration has expired. Please register again.');
        }

        if ($this->cooldownLeft($pending) > 0) {
            throw new DomainException('Please wait a moment before asking for a new code.');
        }

        $code = $this->newCode();

        $this->deliver($pending['name'], $pending['email'], $code);

        $pending['code'] = $this->hashCode($code, $pending['email']);
        $pending['expires_at'] = now()->timestamp + self::CODE_TTL;
        $pending['sent_at'] = now()->timestamp;
        $pending['attempts'] = 0;

        $request->session()->put(self::SESSION_KEY, $pending);
    }

    /** Checks the code and creates the verified member. */
    public function verify(Request $request, string $code): User
    {
        $pending = $this->pending($request);

        if (! $pending) {
            throw new DomainException('Your registration has expired. Please register again.');
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            throw new DomainException('This code has expired. Ask for a new code.');
        }

        if (! hash_equals($pending['code'], $this->hashCode($code, $pending['email']))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $this->forget($request);

                throw new DomainException('Too many wrong codes. Please register again.');
            }

            $request->session()->put(self::SESSION_KEY, $pending);

            throw new DomainException('That code is not correct. Please check your email and try again.');
        }

        if (User::where('email', $pending['email'])->exists()) {
            $this->forget($request);

            throw new DomainException('This email is already registered. Try logging in or use another email.');
        }

        $user = new User();
        $user->forceFill([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'password' => $pending['password'],
            'role_id' => Role::where('role_name', Role::MEMBER)->firstOrFail()->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->forget($request);

        return $user;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** b***@gmail.com, so the page does not show the full address. */
    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function deliver(string $name, string $email, string $code): void
    {
        Mail::to($email)->send(new RegistrationCodeMail($name, $code, intdiv(self::CODE_TTL, 60)));
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
