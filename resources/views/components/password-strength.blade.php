@props(['for' => 'password'])

<div x-data="{
        pwd: '',
        common: ['password', 'password1', 'password123', '12345678', '123456789', 'qwerty123', 'qwertyuiop', 'iloveyou', 'admin123', 'welcome1', 'abc12345'],
        get hasLen() { return this.pwd.length >= 8 },
        get hasLetter() { return /[A-Za-z]/.test(this.pwd) },
        get hasNumber() { return /[0-9]/.test(this.pwd) },
        get isCommon() { return this.common.includes(this.pwd.toLowerCase()) },
        get meetsMin() { return this.hasLen && this.hasLetter && this.hasNumber && !this.isCommon },
        get level() {
            if (!this.pwd) return 0;
            if (!this.meetsMin) return 1;
            const mixed = /[a-z]/.test(this.pwd) && /[A-Z]/.test(this.pwd) && /[^A-Za-z0-9]/.test(this.pwd);
            return (this.pwd.length >= 12 || mixed) ? 3 : 2;
        },
        get label() { return ['', 'Low', 'Medium', 'Strong'][this.level] },
        notify() { this.$dispatch('password-strength', { low: this.level < 2 }) },
    }"
    x-init="
        const form = $root.closest('form');
        const input = form ? form.elements[$root.dataset.for] : null;
        if (input) {
            pwd = input.value;
            input.addEventListener('input', () => { pwd = input.value; notify(); });
        }
        notify();
    "
    data-password-strength
    data-for="{{ $for }}"
    {{ $attributes->merge(['class' => 'space-y-1.5 text-xs']) }}>

    <div class="flex items-center gap-2">
        <div class="flex flex-1 gap-1" aria-hidden="true">
            <span class="h-1.5 flex-1 rounded-full transition-colors"
                :class="level >= 1 ? (level === 1 ? 'bg-red-500' : level === 2 ? 'bg-amber-500' : 'bg-moss') : 'bg-blush'"></span>
            <span class="h-1.5 flex-1 rounded-full transition-colors"
                :class="level >= 2 ? (level === 2 ? 'bg-amber-500' : 'bg-moss') : 'bg-blush'"></span>
            <span class="h-1.5 flex-1 rounded-full transition-colors"
                :class="level >= 3 ? 'bg-moss' : 'bg-blush'"></span>
        </div>
        <span class="w-14 text-right font-semibold text-ink/70" x-text="label" aria-live="polite"></span>
    </div>

    <p x-show="isCommon && pwd" style="display: none" class="text-red-700">This password is too common. Try something more unique.</p>

    <ul class="flex flex-wrap gap-x-3 gap-y-0.5 text-ink/60">
        <li :class="hasLen ? 'text-moss' : ''"><span x-text="hasLen ? '\u2713' : '\u25CB'"></span> At least 8 characters</li>
        <li :class="hasLetter ? 'text-moss' : ''"><span x-text="hasLetter ? '\u2713' : '\u25CB'"></span> Contains a letter</li>
        <li :class="hasNumber ? 'text-moss' : ''"><span x-text="hasNumber ? '\u2713' : '\u25CB'"></span> Contains a number</li>
    </ul>
</div>
