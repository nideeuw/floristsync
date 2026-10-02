<x-staff-layout title="Edit Category">
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="card max-w-xl p-6">
        @csrf
        @method('PUT')
        @include('admin.categories._form', ['category' => $category])

        <div class="mt-6 flex gap-2">
            <button class="btn-primary">Update</button>
            <a href="{{ route('admin.categories.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
