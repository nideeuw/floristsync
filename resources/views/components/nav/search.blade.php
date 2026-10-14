@props(['items' => []])

<div class="relative min-w-0 flex-1 sm:max-w-md"
    x-data="{
        q: '',
        focus: false,
        items: @js($items),
        get results() {
            const s = this.q.trim().toLowerCase();
            return s ? this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(s)).slice(0, 6) : [];
        }
    }"
    x-on:click.outside="focus = false"
    x-on:keydown.window.ctrl.k.prevent="$refs.q.focus()">

    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/50" />

    <input x-ref="q" x-model="q" type="search" autocomplete="off" placeholder="Search menu (Ctrl+K)"
        class="input rounded-full border-transparent bg-cream/40 pl-9 focus:bg-white"
        x-on:focus="focus = true"
        x-on:keydown.enter.prevent="if (results[0]) window.location = results[0].href"
        x-on:keydown.escape="focus = false; $refs.q.blur()">

    <ul x-show="focus && q.trim() !== ''" x-cloak
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
