<?php

namespace App\Support;

class PasswordMessages
{
    public static function translatorLines(): array
    {
        return [
            'validation.password.letters' => 'Your :attribute needs at least one letter.',
            'validation.password.numbers' => 'Your :attribute needs at least one number.',
            'validation.password.uncompromised' => 'This :attribute has appeared in a known data leak. Please choose a different one.',
        ];
    }

    public static function forMember(): array
    {
        return [
            'password.min' => 'Your password needs at least :min characters.',
        ];
    }
}
