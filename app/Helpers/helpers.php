<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(?string $message = null): string
    {
        $url = 'https://wa.me/' . app(\App\Services\SettingService::class)->whatsappNumber();

        return $message ? $url . '?text=' . urlencode($message) : $url;
    }
}
