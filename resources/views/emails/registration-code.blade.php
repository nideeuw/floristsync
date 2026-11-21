<x-mail::message>
# Verify your email

Hello {{ $name }}, use this code to finish creating your {{ config('app.name') }} account.

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

This code is valid for {{ $minutes }} minutes. If you did not try to register, you can ignore this email.
</x-mail::message>
