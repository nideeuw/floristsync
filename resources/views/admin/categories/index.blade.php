<x-staff-layout title="Categories">
    <x-slot:actions>
        <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New Category</a>
    </x-slot:actions>

    <form method="GET" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search category..." class="input max-w-xs">
        <button class="btn-outline">Search</button>
    </form>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blush">
                @forelse ($categories as $category)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $category->category_name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $category->description ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $category->products_count }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-1 text-xs {{ $category->is_active ? 'bg-sage/30' : 'bg-gray-200 text-gray-600' }}">
                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="mr-3 font-semibold text-moss hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline"
                            onsubmit="return confirm('Delete this category?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No categories found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
</x-staff-layout>
