@props(['home' => null])

<aside class="relative hidden overflow-hidden bg-gradient-to-br from-blush via-cream to-petal/50 lg:flex lg:flex-col lg:justify-between lg:p-12">
    <style>
        @keyframes fsPop { 0% { transform: scale(0) rotate(-20deg); opacity: 0; } 100% { transform: scale(1) rotate(0); opacity: 1; } }
        @keyframes fsSway { 0%, 100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg); } }
        @keyframes fsGrow { to { stroke-dashoffset: 0; } }
        @keyframes fsRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes fsFall { 0% { transform: translate3d(0, -10%, 0) rotate(0); opacity: 0; } 10% { opacity: .75; } 90% { opacity: .75; } 100% { transform: translate3d(70px, 105vh, 0) rotate(360deg); opacity: 0; } }
        @keyframes fsDrift { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(30px, 20px); } }
        .fs-stems path { stroke-dasharray: 1; stroke-dashoffset: 1; animation: fsGrow 1.1s ease-out .1s forwards; }
        .fs-leaf, .fs-bloom { transform-box: fill-box; transform-origin: center; }
        .fs-leaf { animation: fsPop .6s cubic-bezier(.3, 1.5, .5, 1) both; }
        .fs-l1 { animation-delay: .8s; } .fs-l2 { animation-delay: .9s; } .fs-l3 { animation-delay: 1s; } .fs-l4 { animation-delay: 1.1s; }
        .fs-bloom { animation: fsPop .8s cubic-bezier(.3, 1.5, .5, 1) both, fsSway 7s ease-in-out infinite; }
        .fs-b1 { animation-delay: .6s, 1.5s; } .fs-b2 { animation-delay: .8s, 2s; } .fs-b3 { animation-delay: 1s, 1.2s; }
        .fs-b4 { animation-delay: 1.2s, 2.4s; } .fs-b5 { animation-delay: 1.4s, 1.8s; }
        .fs-vase { animation: fsRise .9s ease-out both; }
        .fs-petal { position: absolute; top: 0; border-radius: 70% 0 70% 70%; opacity: 0; animation: fsFall linear infinite; }
        .fs-drift { animation: fsDrift 14s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .fs-stems path { animation: none; stroke-dashoffset: 0; }
            .fs-leaf, .fs-bloom, .fs-vase, .fs-drift { animation: none; }
            .fs-petal { display: none; }
        }
    </style>

    <div class="fs-drift pointer-events-none absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/50 blur-3xl"></div>
    <div class="fs-drift pointer-events-none absolute -bottom-24 -right-10 h-96 w-96 rounded-full bg-sage/30 blur-3xl" style="animation-delay: -6s"></div>

    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="fs-petal" style="left: 10%; width: 12px; height: 17px; background: #F6B0BB; animation-duration: 16s; animation-delay: -2s"></span>
        <span class="fs-petal" style="left: 26%; width: 9px; height: 13px; background: #F1C8CB; animation-duration: 19s; animation-delay: -9s"></span>
        <span class="fs-petal" style="left: 42%; width: 14px; height: 20px; background: #E98FA0; animation-duration: 22s; animation-delay: -5s"></span>
        <span class="fs-petal" style="left: 58%; width: 10px; height: 14px; background: #FFFFFF; animation-duration: 17s; animation-delay: -12s"></span>
        <span class="fs-petal" style="left: 72%; width: 12px; height: 17px; background: #F6B0BB; animation-duration: 20s; animation-delay: -7s"></span>
        <span class="fs-petal" style="left: 86%; width: 9px; height: 13px; background: #F1C8CB; animation-duration: 18s; animation-delay: -14s"></span>
    </div>

    <x-brand-mark :href="$home ?? url('/')" size="lg" class="relative" />

    <div class="relative mx-auto w-full max-w-xs animate-float-slow">
        <x-bouquet />
    </div>

    <div class="relative">
        <p class="max-w-sm animate-fade-up font-display text-4xl font-semibold italic leading-tight text-ink" style="animation-delay: .4s">Flowers that say it from the heart.</p>
        <x-copyright class="mt-6 block text-xs text-ink/40" />
    </div>
</aside>