<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
