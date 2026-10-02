<x-staff-layout title="New Category">
    <form method="POST" action="{{ route('admin.categories.store') }}" class="card max-w-xl p-6">
        @csrf
        @include('admin.categories._form')

        <div class="mt-6 flex gap-2">
            <button class="btn-primary">Save</button>
            <a href="{{ route('admin.categories.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
