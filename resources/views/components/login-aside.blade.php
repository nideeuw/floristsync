@props(['variant' => 'bloom', 'dark' => false, 'home' => null, 'badge' => null, 'headline' => null])

@php
$home = $home ?? url('/');
$accent = $dark ? 'text-petal' : 'text-moss';
$panel = $dark
    ? 'border-r border-white/10 bg-ink text-cream'
    : 'border-r border-ink/10 bg-gradient-to-br from-blush/50 via-cream to-paper text-ink';
@endphp

@if ($variant === 'quotes')

{{-- Quotes: florist lines that rotate, with a progress bar and drifting petals --}}
<aside class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12 {{ $panel }}">
    <style>
        @keyframes fsqSway { 0%, 100% { transform: rotate(-4deg) translateY(0); } 50% { transform: rotate(4deg) translateY(-14px); } }
        @keyframes fsqFill { to { transform: scaleX(1); } }
        @keyframes fsqFall { 0% { transform: translate3d(0, -10%, 0) rotate(0); opacity: 0; } 10% { opacity: .6; } 90% { opacity: .6; } 100% { transform: translate3d(70px, 105vh, 0) rotate(360deg); opacity: 0; } }
        @keyframes fsqRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .fsq-mark { animation: fsqSway 16s ease-in-out infinite; }
        .fsq-bar { transform-origin: left; transform: scaleX(0); }
        .fsq-dot.is-active .fsq-bar { animation: fsqFill 4.9s linear forwards; }
        .fsq-petal { position: absolute; top: 0; border-radius: 70% 0 70% 70%; opacity: 0; animation: fsqFall linear infinite; }
        .fsq-rise { animation: fsqRise .9s cubic-bezier(.2, .7, .2, 1) both; }
        @media (prefers-reduced-motion: reduce) {
            .fsq-mark, .fsq-rise { animation: none; }
            .fsq-petal { display: none; }
            .fsq-dot.is-active .fsq-bar { animation: none; transform: scaleX(1); }
        }
    </style>

    <div class="pointer-events-none absolute -bottom-28 -right-24 w-[28rem] rotate-12 select-none {{ $dark ? 'opacity-[0.06]' : 'opacity-20' }}">
        <img src="{{ asset('images/logo.png') }}" alt="" class="fsq-mark w-full">
    </div>

    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="fsq-petal" style="left: 12%; width: 12px; height: 17px; background: #F6B0BB; animation-duration: 17s; animation-delay: -3s"></span>
        <span class="fsq-petal" style="left: 30%; width: 9px; height: 13px; background: #F1C8CB; animation-duration: 20s; animation-delay: -10s"></span>
        <span class="fsq-petal" style="left: 48%; width: 14px; height: 20px; background: #E98FA0; animation-duration: 23s; animation-delay: -6s"></span>
        <span class="fsq-petal" style="left: 66%; width: 10px; height: 14px; background: #FFFFFF; animation-duration: 18s; animation-delay: -13s"></span>
        <span class="fsq-petal" style="left: 84%; width: 12px; height: 17px; background: #F6B0BB; animation-duration: 21s; animation-delay: -8s"></span>
    </div>

    <x-brand-mark :href="$home" size="lg" :light="$dark" class="relative" />

    <div class="relative max-w-md">
        <p id="fsQuote" class="min-h-[10rem] font-display text-5xl font-semibold italic leading-[1.15] transition-all duration-[600ms]">Every bouquet starts at the counter.</p>
        <div class="mt-6 flex gap-2 {{ $accent }}">
            @foreach (range(0, 3) as $n)
            <span data-fsq-dot class="fsq-dot relative h-1.5 w-8 overflow-hidden rounded-full {{ $dark ? 'bg-cream/20' : 'bg-ink/15' }}">
                <span class="fsq-bar absolute inset-0 rounded-full bg-current"></span>
            </span>
            @endforeach
        </div>
    </div>

    <x-copyright class="relative text-xs {{ $dark ? 'text-cream/40' : 'text-ink/40' }}" />

    <script>
        (function () {
            var lines = ['Every bouquet starts at the counter.', 'Fresh stems, careful hands.', 'A little ribbon makes it a gift.', 'Smile first, then ring it up.'];
            var el = document.getElementById('fsQuote');
            var dots = document.querySelectorAll('[data-fsq-dot]');
            var i = 0;
            function mark(n) { dots.forEach(function (d, k) { d.classList.toggle('is-active', k === n); }); }
            mark(0);
            setInterval(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(function () {
                    i = (i + 1) % lines.length;
                    el.textContent = lines[i];
                    el.style.transition = 'none';
                    el.style.transform = 'translateY(10px)';
                    void el.offsetWidth;
                    el.style.transition = '';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                    mark(i);
                }, 600);
            }, 5500);
        })();
    </script>
</aside>

@else

{{-- Bloom: full-bleed, slowly turning flowers and drifting petals. Drop a photo at public/images/login.jpg and it replaces the flowers --}}
<aside class="relative hidden overflow-hidden bg-ink text-cream lg:flex lg:flex-col lg:justify-between lg:p-12">
    <style>
        @keyframes fsbSpin { to { transform: rotate(360deg); } }
        @keyframes fsbSpinRev { to { transform: rotate(-360deg); } }
        @keyframes fsbFall { 0% { transform: translate3d(0, -10%, 0) rotate(0); opacity: 0; } 10% { opacity: .5; } 90% { opacity: .5; } 100% { transform: translate3d(70px, 105vh, 0) rotate(360deg); opacity: 0; } }
        @keyframes fsbRise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        .fsb-spin { animation: fsbSpin 90s linear infinite; }
        .fsb-spin-rev { animation: fsbSpinRev 70s linear infinite; }
        .fsb-petal { position: absolute; top: 0; border-radius: 70% 0 70% 70%; opacity: 0; animation: fsbFall linear infinite; }
        .fsb-rise { animation: fsbRise .9s cubic-bezier(.2, .7, .2, 1) both; }
        @media (prefers-reduced-motion: reduce) {
            .fsb-spin, .fsb-spin-rev, .fsb-rise { animation: none; }
            .fsb-petal { display: none; }
        }
    </style>

    @if (file_exists(public_path('images/login.jpg')))
    <img src="{{ asset('images/login.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
    @else
    <div class="absolute inset-0 bg-gradient-to-br from-[#2b3426] via-ink to-moss/60"></div>
    <div class="pointer-events-none absolute -right-20 top-16 w-[26rem] rotate-12">
        <svg viewBox="0 0 100 100" class="fsb-spin w-full text-petal opacity-25" aria-hidden="true">
            <g fill="currentColor">
                <circle cx="50" cy="22" r="17" /><circle cx="76.6" cy="41.4" r="17" /><circle cx="66.5" cy="72.7" r="17" />
                <circle cx="33.5" cy="72.7" r="17" /><circle cx="23.4" cy="41.4" r="17" />
            </g>
            <circle cx="50" cy="50" r="9" fill="#ECE0DA" />
        </svg>
    </div>
    <div class="pointer-events-none absolute -bottom-16 left-24 w-72 -rotate-12">
        <svg viewBox="0 0 100 100" class="fsb-spin-rev w-full text-sage opacity-20" aria-hidden="true">
            <g fill="currentColor">
                <circle cx="50" cy="22" r="17" /><circle cx="76.6" cy="41.4" r="17" /><circle cx="66.5" cy="72.7" r="17" />
                <circle cx="33.5" cy="72.7" r="17" /><circle cx="23.4" cy="41.4" r="17" />
            </g>
            <circle cx="50" cy="50" r="9" fill="#ECE0DA" />
        </svg>
    </div>
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-ink/90 via-ink/30 to-ink/40"></div>

    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="fsb-petal" style="left: 14%; width: 12px; height: 17px; background: #F6B0BB; animation-duration: 18s; animation-delay: -4s"></span>
        <span class="fsb-petal" style="left: 34%; width: 9px; height: 13px; background: #F1C8CB; animation-duration: 21s; animation-delay: -11s"></span>
        <span class="fsb-petal" style="left: 52%; width: 14px; height: 20px; background: #E98FA0; animation-duration: 24s; animation-delay: -7s"></span>
        <span class="fsb-petal" style="left: 70%; width: 10px; height: 14px; background: #F6B0BB; animation-duration: 19s; animation-delay: -14s"></span>
        <span class="fsb-petal" style="left: 88%; width: 12px; height: 17px; background: #F1C8CB; animation-duration: 22s; animation-delay: -9s"></span>
    </div>

    <x-brand-mark :href="$home" size="lg" light class="relative" />

    <div class="relative max-w-md">
        @if ($badge)
        <p class="fsb-rise flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.25em] text-petal" style="animation-delay: .1s">
            <span class="h-px w-8 bg-current"></span>{{ $badge }}
        </p>
        @endif
        @if ($headline)
        <h2 class="fsb-rise mt-5 font-display text-4xl font-semibold leading-[1.15]" style="animation-delay: .25s">{{ $headline }}</h2>
        @endif
        <x-copyright class="fsb-rise mt-8 block text-xs text-cream/50" style="animation-delay: .4s" />
    </div>
</aside>

@endif