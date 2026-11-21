<x-mail::message>
# Confirm your new email

Hello {{ $name }}, use this code to confirm this address for your {{ config('app.name') }} account.

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

This code is valid for {{ $minutes }} minutes. If you did not ask to change your email, you can ignore this message and your account stays as it is.
</x-mail::message>
