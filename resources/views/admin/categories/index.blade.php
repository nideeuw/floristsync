<x-staff-layout title="Categories">
    <x-slot:actions>
        <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New Category</a>
    </x-slot:actions>

    <form method="GET" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search category..." class="input max-w-xs">
        <button class="btn-outline">Search</button>
    </form>

    <x-ui.table>
        <x-slot:head>
            <th>Name</th>
            <th>Description</th>
            <th>Products</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($categories as $category)
            <tr>
                <td class="font-medium">{{ $category->category_name }}</td>
                <td class="text-gray-600">{{ $category->description ?? '-' }}</td>
                <td>{{ $category->products_count }}</td>
                <td>
                    <span class="rounded-full px-2 py-1 text-xs {{ $category->is_active ? 'bg-sage/30' : 'bg-gray-200 text-gray-600' }}">
                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="whitespace-nowrap text-right">
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
                <td colspan="5" class="!py-8 text-center text-gray-500">No categories found.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $categories->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
