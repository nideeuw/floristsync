@props(['items' => []])

<div class="shrink-0"
    x-data="{
        open: false,
        q: '',
        items: @js($items),
        get results() {
            const s = this.q.trim().toLowerCase();
            return s ? this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(s)).slice(0, 6) : [];
        },
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.q.focus());
        },
        hide() {
            this.open = false;
            this.q = '';
        }
    }"
    x-on:keydown.window.ctrl.k.prevent="show()"
    x-on:keydown.escape.window="open && hide()">

    <button type="button" x-on:click.stop="show()" aria-label="Search menu (Ctrl+K)" title="Search (Ctrl+K)"
        class="rounded-full bg-white p-2.5 text-ink/70 ring-1 ring-blush/60 transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage">
        <x-ui.icon name="search" class="h-5 w-5" />
    </button>

    {{-- Covers the whole header bar (positioned against the <header>, which is relative) --}}
    <div x-show="open" x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0 scale-x-50"
        x-transition:enter-end="opacity-100 scale-x-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click.outside="open && hide()"
        class="absolute inset-0 z-30 flex origin-left items-center bg-shell px-4 sm:px-6">

        <div class="relative w-full">
            <x-ui.icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-ink/50" />

            <input x-ref="q" x-model="q" type="search" autocomplete="off" placeholder="Search menu..."
                class="input h-11 rounded-full border-transparent bg-white pl-12 pr-12 shadow-md ring-1 ring-blush/60"
                x-on:keydown.enter.prevent="if (results[0]) window.location = results[0].href">

            <button type="button" x-on:click="hide()" aria-label="Close search"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-ink/50 transition-colors hover:bg-shell hover:text-ink">
                <x-ui.icon name="x" class="h-4 w-4" />
            </button>

            <ul x-show="q.trim() !== ''" x-cloak
                class="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-2xl border border-blush bg-white p-1 shadow-lg">
                <template x-for="r in results" :key="r.href">
                    <li>
                        <a :href="r.href" class="flex flex-col rounded-xl px-3 py-2 text-sm hover:bg-blush">
                            <span class="font-semibold" x-text="r.label"></span>
                            <span class="text-xs text-ink/60" x-text="r.group"></span>
                        </a>
                    </li>
                </template>
                <li x-show="results.length === 0" class="px-3 py-2 text-sm text-ink/60">No menu found.</li>
            </ul>
        </div>
    </div>
</div>