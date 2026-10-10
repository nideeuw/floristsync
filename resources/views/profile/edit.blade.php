<x-public-layout title="My Profile">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10">
        <h1 class="text-3xl font-bold">My Profile</h1>

        <div class="card p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-public-layout>
