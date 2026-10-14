@php
$categoryIcons = ['flower-2', 'flower', 'sprout', 'leaf', 'heart', 'gift'];
$categoryTints = ['bg-blush/70', 'bg-sage/30', 'bg-petal/40', 'bg-cream'];
$lowStock = (int) config('floristsync.low_stock_threshold', 5);
$marquee = ['Hand bouquets', 'Flower boxes', 'Standing flowers', 'Weddings', 'Graduations', 'Birthdays', 'Anniversaries', 'Condolences'];
$trust = [
['icon' => 'leaf', 'title' => 'Fresh every day', 'text' => 'Arranged by our florists, never stored for long.'],
['icon' => 'truck', 'title' => 'Pickup or delivery', 'text' => 'Collect in store or let a courier bring it.'],
['icon' => 'shield-check', 'title' => 'Secure payment', 'text' => 'Card, QRIS and e-wallet through Midtrans.'],
['icon' => 'message-circle', 'title' => 'Friendly support', 'text' => 'Chat with us on WhatsApp anytime.'],
];
$steps = [
['no' => '01', 'title' => 'Pick your flowers', 'text' => 'Browse bouquets, flower boxes and standing flowers made for every occasion.'],
['no' => '02', 'title' => 'Check out securely', 'text' => 'Add a card message, choose pickup or delivery, and pay online.'],
['no' => '03', 'title' => 'Receive with care', 'text' => 'We arrange it fresh, then you collect it or we ship it to your door.'],
];
@endphp

<x-public-layout>
    {{-- Hero --}}
    <section class="mx-auto max-w-7xl px-4 pt-2 md:pt-4">
        <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-blush/80 via-cream to-petal/40">
            <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-white/50 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-28 right-10 h-80 w-80 rounded-full bg-sage/30 blur-3xl"></div>

            <div class="relative grid items-center gap-10 px-6 py-12 sm:px-10 md:py-16 lg:grid-cols-2 lg:px-16">
                <div>
                    <span class="eyebrow animate-fade-up">
                        <span class="h-px w-8 bg-moss"></span> Fresh every day
                    </span>
                    <h1 class="animate-fade-up mt-5 font-display text-5xl font-semibold leading-[0.98] text-ink sm:text-6xl lg:text-7xl"
                        style="animation-delay: 120ms">
                        Flowers that say it <em class="italic text-moss">from the heart</em>
                    </h1>
                    <p class="animate-fade-up mt-6 max-w-md text-base leading-relaxed text-ink/70 md:text-lg"
                        style="animation-delay: 240ms">
                        Bouquets, flower boxes and standing flowers, arranged by hand and ready to pick up in store or delivered to your door.
                    </p>
                    <div class="animate-fade-up mt-8 flex flex-wrap items-center gap-3" style="animation-delay: 360ms">
                        <a href="{{ route('catalog') }}" class="btn-primary px-7 py-3.5 text-base">
                            Shop now
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a href="{{ whatsapp_url() }}" target="_blank" rel="noopener"
                            class="btn-outline bg-white/40 px-7 py-3.5 text-base">
                            Custom order
                        </a>
                    </div>
                    @guest
                    <p class="animate-fade-up mt-5 text-sm text-ink/60" style="animation-delay: 460ms">
                        New here?
                        <a href="{{ route('register') }}" class="font-semibold text-moss underline-offset-4 hover:underline">Create an account</a>
                        to check out faster.
                    </p>
                    @endguest
                </div>

                {{-- Illustration --}}
                <div class="relative mx-auto w-full max-w-sm lg:max-w-md">
                    <div class="animate-float-slow aspect-[480/560] w-full">
                        <svg viewBox="0 0 480 560" class="h-full w-full" role="img"
                            aria-label="Illustration of a flower bouquet in a vase" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="fsArch" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#E4EADC" />
                                    <stop offset="1" stop-color="#AEBD9F" />
                                </linearGradient>
                                <linearGradient id="fsVase" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0" stop-color="#FFFFFF" />
                                    <stop offset="0.55" stop-color="#F7EEE9" />
                                    <stop offset="1" stop-color="#E3D2CA" />
                                </linearGradient>
                                <g id="fsBloom">
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" />
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" transform="rotate(60)" />
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" transform="rotate(120)" />
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" transform="rotate(180)" />
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" transform="rotate(240)" />
                                    <ellipse cx="0" cy="-24" rx="14" ry="24" transform="rotate(300)" />
                                    <circle r="9" fill="#F4D58D" stroke="none" />
                                </g>
                                <path id="fsLeaf" d="M0 0C16-16 42-16 60 0 42 16 16 16 0 0Z" />
                            </defs>

                            <path d="M24 560V250A216 216 0 0 1 456 250V560Z" fill="url(#fsArch)" />

                            <g fill="none" stroke="#7E8C69" stroke-width="5" stroke-linecap="round">
                                <path d="M240 400C240 330 240 230 240 150" />
                                <path d="M240 400C220 330 175 270 150 205" />
                                <path d="M240 400C262 330 305 265 335 200" />
                                <path d="M240 400C225 350 200 320 188 285" />
                                <path d="M240 400C255 350 282 320 296 280" />
                                <path d="M240 400C215 360 140 330 112 300" />
                                <path d="M240 400C270 360 345 330 372 296" />
                            </g>

                            <g fill="#8FA07C">
                                <use href="#fsLeaf" transform="translate(238 345) rotate(-140) scale(0.9)" />
                                <use href="#fsLeaf" transform="translate(242 330) rotate(-35) scale(0.9)" />
                                <use href="#fsLeaf" transform="translate(200 300) rotate(-165) scale(0.7)" />
                                <use href="#fsLeaf" transform="translate(285 300) rotate(-15) scale(0.7)" />
                                <use href="#fsLeaf" transform="translate(165 250) rotate(-120) scale(0.6)" />
                                <use href="#fsLeaf" transform="translate(318 245) rotate(-60) scale(0.6)" />
                            </g>

                            <g stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="1.5">
                                <use href="#fsBloom" fill="#F6B0BB" transform="translate(240 150) scale(1.35)" />
                                <use href="#fsBloom" fill="#FFFFFF" transform="translate(150 205) scale(1.1)" />
                                <use href="#fsBloom" fill="#E98FA0" transform="translate(335 200) scale(1.15)" />
                                <use href="#fsBloom" fill="#F1C8CB" transform="translate(188 285)" />
                                <use href="#fsBloom" fill="#FFFFFF" transform="translate(296 280) scale(0.95)" />
                                <use href="#fsBloom" fill="#F6B0BB" transform="translate(112 300) scale(0.85)" />
                                <use href="#fsBloom" fill="#F1C8CB" transform="translate(372 296) scale(0.85)" />
                            </g>

                            <ellipse cx="240" cy="548" rx="92" ry="9" fill="#374030" opacity="0.12" />
                            <path d="M196 392C190 440 150 470 168 520C176 545 304 545 312 520C330 470 290 440 284 392Z" fill="url(#fsVase)" />
                            <ellipse cx="240" cy="392" rx="44" ry="9" fill="#E3D2CA" />
                        </svg>
                    </div>

                    <div class="animate-float absolute -left-2 top-10 flex items-center gap-3 rounded-2xl bg-white/90 px-4 py-3 shadow-xl backdrop-blur sm:-left-8">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blush text-moss">
                            <x-ui.icon name="sparkles" class="h-5 w-5" />
                        </span>
                        <div class="text-xs leading-tight">
                            <div class="font-semibold">Hand arranged</div>
                            <div class="text-ink/60">by our florists</div>
                        </div>
                    </div>

                    <div class="animate-float-slow absolute -right-2 bottom-16 flex items-center gap-3 rounded-2xl bg-white/90 px-4 py-3 shadow-xl backdrop-blur sm:-right-8">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sage/40 text-moss">
                            <x-ui.icon name="truck" class="h-5 w-5" />
                        </span>
                        <div class="text-xs leading-tight">
                            <div class="font-semibold">Pickup or delivery</div>
                            <div class="text-ink/60">your choice</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Marquee --}}
    <section class="mt-8 overflow-hidden border-y border-blush/70 bg-white/60 py-4" aria-hidden="true">
        <div class="flex w-max animate-marquee">
            @for ($i = 0; $i < 2; $i++)
            <div class="flex shrink-0 items-center gap-10 pr-10 font-display text-2xl italic text-ink/70">
                @foreach ($marquee as $word)
                <span>{{ $word }}</span>
                <x-ui.icon name="flower-2" class="h-5 w-5 text-petal" />
                @endforeach
            </div>
            @endfor
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-16 md:py-24">
        <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
            <div>
                <span class="eyebrow">Collections</span>
                <h2 class="section-title mt-3">Shop by category</h2>
            </div>
            <a href="{{ route('catalog') }}" class="btn-outline">
                View all
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($categories as $category)
            <a href="{{ route('catalog', ['category' => $category->id]) }}" data-reveal
                style="--d: {{ ($loop->index % 4) * 80 }}ms"
                class="group relative flex min-h-[11rem] flex-col justify-end overflow-hidden rounded-3xl p-5 ring-1 ring-white/70 transition duration-500 hover:-translate-y-1 hover:shadow-xl md:min-h-[14rem] {{ $categoryTints[$loop->index % count($categoryTints)] }}">
                <x-ui.icon :name="$categoryIcons[$loop->index % count($categoryIcons)]"
                    class="absolute -right-5 -top-5 h-28 w-28 text-white/70 transition duration-700 group-hover:rotate-12 group-hover:scale-110 md:h-36 md:w-36" />
                <span class="text-xs font-semibold uppercase tracking-widest text-moss">{{ $category->products_count }} items</span>
                <h3 class="mt-1 font-display text-2xl font-semibold leading-tight md:text-3xl">{{ $category->category_name }}</h3>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-moss">
                    Explore
                    <x-ui.icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" />
                </span>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Featured products --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 md:pb-24">
        <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
            <div>
                <span class="eyebrow">New arrivals</span>
                <h2 class="section-title mt-3">Fresh from our studio</h2>
                <p class="mt-3 max-w-md text-ink/60">The latest arrangements, made by hand and ready for your special moment.</p>
            </div>
            <a href="{{ route('catalog') }}" class="btn-outline">
                Browse catalog
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        @if ($featured->isEmpty())
        <x-ui.empty-state class="mt-10" icon="flower-2" title="New arrangements are coming soon"
            description="Our florists are preparing something beautiful. Please check back shortly." />
        @else
        <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($featured as $product)
            <article class="group" data-reveal style="--d: {{ ($loop->index % 4) * 80 }}ms">
                <a href="{{ route('products.show', $product) }}" class="block">
                    <div class="relative overflow-hidden rounded-3xl bg-cream ring-1 ring-blush/60 transition duration-500 group-hover:-translate-y-1 group-hover:shadow-xl">
                        <x-product-image :product="$product"
                            class="aspect-[4/5] w-full transition duration-700 ease-out group-hover:scale-105" />

                        @if ($product->category)
                        <span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-moss backdrop-blur">
                            {{ $product->category->category_name }}
                        </span>
                        @endif

                        @if ($product->stock > 0 && $product->stock <= $lowStock)
                        <span class="absolute bottom-3 left-3 rounded-full bg-ink/80 px-3 py-1 text-[11px] font-semibold text-cream backdrop-blur">
                            Only {{ $product->stock }} left
                        </span>
                        @endif

                        <span class="absolute bottom-3 right-3 flex h-10 w-10 items-center justify-center rounded-full bg-white text-moss opacity-100 shadow-lg transition duration-300 md:translate-y-2 md:opacity-0 md:group-hover:translate-y-0 md:group-hover:opacity-100">
                            <x-ui.icon name="arrow-up-right" class="h-5 w-5" />
                        </span>
                    </div>

                    <div class="mt-4 px-1">
                        <h3 class="font-display text-xl font-semibold leading-snug md:text-2xl">{{ $product->product_name }}</h3>
                        <p class="mt-1 text-sm font-semibold text-moss">{{ rupiah($product->price) }}</p>
                    </div>
                </a>
            </article>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Trust strip --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 md:pb-24">
        <div class="grid gap-6 rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-blush/60 sm:grid-cols-2 lg:grid-cols-4" data-reveal>
            @foreach ($trust as $item)
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blush/60 text-moss">
                    <x-ui.icon :name="$item['icon']" class="h-6 w-6" />
                </span>
                <div>
                    <h3 class="font-semibold">{{ $item['title'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-ink/60">{{ $item['text'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="mx-auto max-w-7xl px-4 pb-16 md:pb-24">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div class="relative mx-auto w-full max-w-md overflow-hidden rounded-[2.5rem] bg-moss p-8 pb-0 text-white" data-reveal>
                <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-sage/40 blur-2xl"></div>
                <p class="relative font-display text-3xl font-semibold italic leading-snug md:text-4xl">
                    Every bouquet is arranged by hand, one order at a time.
                </p>
                <svg viewBox="0 0 320 220" class="relative mt-6 w-full" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                    <g fill="none" stroke="#DCE4D3" stroke-width="4" stroke-linecap="round">
                        <path d="M160 220C160 170 160 120 160 80" />
                        <path d="M160 220C140 175 110 140 90 110" />
                        <path d="M160 220C182 175 215 140 236 108" />
                    </g>
                    <g stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="1.5">
                        <use href="#fsBloom" fill="#F6B0BB" transform="translate(160 70) scale(1.1)" />
                        <use href="#fsBloom" fill="#FFFFFF" transform="translate(88 104) scale(0.9)" />
                        <use href="#fsBloom" fill="#F1C8CB" transform="translate(236 102) scale(0.9)" />
                    </g>
                </svg>
            </div>

            <div>
                <span class="eyebrow" data-reveal>From idea to doorstep</span>
                <h2 class="section-title mt-3" data-reveal>How it works</h2>

                <ol class="mt-8 divide-y divide-blush/80">
                    @foreach ($steps as $step)
                    <li class="flex gap-6 py-6" data-reveal style="--d: {{ $loop->index * 100 }}ms">
                        <span class="font-display text-5xl font-semibold leading-none text-petal">{{ $step['no'] }}</span>
                        <div>
                            <h3 class="text-lg font-semibold">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-ink/60">{{ $step['text'] }}</p>
                        </div>
                    </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    

    {{-- CTA --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 md:pt-24">
        <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-moss to-ink px-6 py-16 text-center text-white md:py-20" data-reveal>
            <div class="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-petal/20 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -right-10 h-72 w-72 rounded-full bg-sage/30 blur-2xl"></div>
            <x-ui.icon name="flower-2" class="pointer-events-none absolute -right-6 top-6 h-40 w-40 text-white/10" />
            <x-ui.icon name="flower" class="pointer-events-none absolute -left-4 bottom-4 h-32 w-32 text-white/10" />

            <div class="relative mx-auto max-w-2xl">
                <h2 class="font-display text-4xl font-semibold leading-tight md:text-6xl">Need a custom arrangement?</h2>
                <p class="mt-4 text-white/80 md:text-lg">Tell us your idea, colours and budget, and we will make it happen.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ whatsapp_url() }}" target="_blank" rel="noopener" class="btn-light px-7 py-3.5 text-base">
                        <x-ui.icon name="message-circle" class="h-4 w-4" />
                        Chat on WhatsApp
                    </a>
                    <a href="{{ route('catalog') }}" class="btn-ghost-light px-7 py-3.5 text-base">Browse catalog</a>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>
