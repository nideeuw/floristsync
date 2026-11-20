<x-member-auth title="Reactivate account" heading="Welcome back"
    subtitle="Your account was deactivated. Reactivate it to continue shopping and see your order history." :home="route('home')">

    <div class="rounded-2xl bg-blush/40 px-4 py-3 text-sm">
        <p class="font-medium">{{ $member->name }}</p>
        <p class="text-ink/60">{{ $member->email }}</p>
    </div>

    <form method="POST" action="{{ route('account.reactivate.store') }}" class="mt-6 space-y-4">
        @csrf

        <button class="btn-primary w-full py-3.5 text-base">Reactivate my account</button>

        <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-moss hover:text-ink">
            Cancel
        </a>
    </form>
</x-member-auth>
