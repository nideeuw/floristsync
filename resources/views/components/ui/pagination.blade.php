@php
    $sizes = $sizes ?? false;
    $sizeOptions = collect($sizeOptions ?? [10, 25, 50, 100])
        ->push($paginator->perPage())->unique()->sort()->values();
    $box = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm transition';
    $hasNumbers = collect($elements)->contains(fn ($element) => is_array($element));
@endphp

@if ($paginator->total() > 0 || $sizes)
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @if ($paginator->total() > 0)
                <div class="flex flex-wrap items-center gap-1">
                    {{-- Previous --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" class="{{ $box }} cursor-not-allowed border-blush/60 text-ink/30">Previous</span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                            class="{{ $box }} border-blush text-ink hover:bg-blush/50">Previous</a>
                    @endif

                    {{-- Page numbers --}}
                    @unless ($hasNumbers)
                        <span aria-current="page" class="{{ $box }} border-moss bg-moss font-semibold text-white">1</span>
                    @endunless

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="{{ $box }} border-transparent text-ink/50">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page"
                                        class="{{ $box }} border-moss bg-moss font-semibold text-white">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                        class="{{ $box }} border-blush text-ink hover:bg-blush/50">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                            class="{{ $box }} border-blush text-ink hover:bg-blush/50">Next</a>
                    @else
                        <span aria-disabled="true" class="{{ $box }} cursor-not-allowed border-blush/60 text-ink/30">Next</span>
                    @endif
                </div>
            @endif

            @if ($sizes)
                <label class="flex items-center gap-2 text-sm text-ink/70">
                    Show
                    <select aria-label="Entries per page" class="input !w-auto !py-1.5"
                        onchange="const u = new URL(window.location.href); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); window.location.href = u.toString();">
                        @foreach ($sizeOptions as $size)
                            <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    entries
                </label>
            @endif
        </div>

        <p class="text-sm text-ink/60">
            Showing
            <span class="font-semibold text-ink">{{ $paginator->firstItem() ?? 0 }}</span>
            to
            <span class="font-semibold text-ink">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-semibold text-ink">{{ $paginator->total() }}</span>
            entries
        </p>
    </nav>
@endif
