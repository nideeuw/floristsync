<div x-data="{
        open: false,
        form: null,
        title: 'Are you sure?',
        message: '',
        label: 'Confirm',
        cancelLabel: 'Cancel',
        tone: 'primary',
        ask(form) {
            this.title = form.dataset.confirmTitle || 'Are you sure?';
            this.message = form.dataset.confirm || '';
            this.label = form.dataset.confirmLabel || 'Confirm';
            this.cancelLabel = form.dataset.confirmCancelLabel || 'Cancel';
            this.tone = form.dataset.confirmTone || 'primary';
            this.form = form;
            this.open = true;
        },
        accept() {
            const form = this.form;
            this.close();
            if (form) form.submit();
        },
        close() {
            this.open = false;
            this.form = null;
        },
    }"
    x-init="document.addEventListener('submit', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement) || ! form.dataset.confirm) return;
        event.preventDefault();
        ask(form);
    })"
    x-effect="if (open) $nextTick(() => $refs.keep && $refs.keep.focus())"
    @keydown.escape.window="if (open) close()">

    <div x-show="open" x-cloak
        class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
        role="dialog" aria-modal="true" aria-labelledby="confirm-title">

        <div x-show="open" x-transition.opacity @click="close()"
            class="absolute inset-0 bg-ink/40 backdrop-blur-sm"></div>

        <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-xl ring-1 ring-blush">

            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                    :class="tone === 'danger' ? 'bg-red-100 text-red-700' : 'bg-sage/30 text-moss'">
                    <span x-show="tone === 'danger'"><x-ui.icon name="triangle-alert" class="h-5 w-5" /></span>
                    <span x-show="tone !== 'danger'"><x-ui.icon name="info" class="h-5 w-5" /></span>
                </span>

                <div class="min-w-0">
                    <h2 id="confirm-title" class="font-display text-2xl font-semibold leading-tight" x-text="title"></h2>
                    <p class="mt-1.5 break-words text-sm text-ink/70" x-text="message"></p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" x-ref="keep" @click="close()" class="btn-outline" x-text="cancelLabel"></button>
                <button type="button" @click="accept()"
                    :class="tone === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-moss hover:bg-ink'"
                    class="inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold text-white transition"
                    x-text="label"></button>
            </div>
        </div>
    </div>
</div>
