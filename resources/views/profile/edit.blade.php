<x-public-layout title="My Profile">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 md:py-12">
        <div class="flex items-center gap-4">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-blush font-display text-3xl font-semibold text-moss">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <span class="eyebrow">My account</span>
                <h1 class="truncate font-display text-4xl font-semibold leading-tight md:text-5xl">{{ auth()->user()->name }}</h1>
            </div>
        </div>

        <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-blush/60 md:p-8">
            @include('profile.partials.update-password-form')
        </div>

        <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-red-200 md:p-8">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-public-layout>
