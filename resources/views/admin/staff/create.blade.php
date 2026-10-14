<x-staff-layout title="Add Staff">
    <x-slot:actions>
        <a href="{{ route('admin.staff.index') }}" class="btn-outline">&larr; Back to staff</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.staff.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @include('admin.staff._form')

        <div class="flex gap-2">
            <button class="btn-primary">Create staff</button>
            <a href="{{ route('admin.staff.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
