<?php

use App\Services\SettingService;

if (! function_exists('rupiah')) {
    function rupiah(int|float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(?string $message = null): string
    {
        $url = 'https://wa.me/'.app(SettingService::class)->whatsappNumber();

        return $message ? $url.'?text='.urlencode($message) : $url;
    }
}

if (! function_exists('escape_like')) {
    /**
     * Escape %, _, and \ in a search term for LIKE / ILIKE queries,
     * trim whitespace, and cap at the specified maximum length (default 100).
     */
    function escape_like(?string $value, int $max = 100): string
    {
        $trimmed = mb_substr(trim((string) $value), 0, $max);

        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $trimmed
        );
    }
}
