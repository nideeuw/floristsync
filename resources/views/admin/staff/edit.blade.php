<x-staff-layout :title="'Edit ' . $staff->name">
    <x-slot:actions>
        <a href="{{ route('admin.staff.index') }}" class="btn-outline">&larr; Back to staff</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @method('PUT')
        @include('admin.staff._form')

        <div class="flex gap-2">
            <button class="btn-primary">Save changes</button>
            <a href="{{ route('admin.staff.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
