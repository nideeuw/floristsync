<x-staff-layout title="New Product">
    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data"
        class="card max-w-3xl p-6">
        @csrf
        @include('admin.products._form')

        <div class="mt-6 flex gap-2">
            <button class="btn-primary">Save</button>
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</x-staff-layout>
