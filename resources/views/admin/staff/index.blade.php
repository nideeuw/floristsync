<x-staff-layout title="Staff Accounts">
    <x-slot:actions>
        <a href="{{ route('admin.staff.create') }}" class="btn-primary">Add staff</a>
    </x-slot:actions>

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.staff.index') }}" class="card mb-4 grid gap-3 p-4 md:grid-cols-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email" class="input">

        <select name="role_id" class="input">
            <option value="">All roles</option>
            @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>
                {{ \Illuminate\Support\Str::headline($role->role_name) }}
            </option>
            @endforeach
        </select>

        <select name="status" class="input">
            <option value="">Active and inactive</option>
            <option value="active" @selected(request('status')==='active' )>Active</option>
            <option value="inactive" @selected(request('status')==='inactive' )>Inactive</option>
        </select>

        <div class="flex gap-2">
            <button class="btn-primary">Filter</button>
            <a href="{{ route('admin.staff.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <x-ui.table class="[&_table]:min-w-[44rem]">
        <x-slot:head>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th class="text-right">Action</th>
        </x-slot:head>

        <x-slot:body>
            @forelse ($staff as $member)
            <tr>
                <td>
                    <div class="font-semibold">{{ $member->name }}</div>
                    @if ($member->phone)
                    <div class="text-xs text-gray-500">{{ $member->phone }}</div>
                    @endif
                </td>
                <td>{{ $member->email }}</td>
                <td>{{ \Illuminate\Support\Str::headline($member->role?->role_name ?? '-') }}</td>
                <td>
                    @if ($member->is_active)
                    <span class="rounded-full bg-sage/30 px-2.5 py-0.5 text-xs font-semibold">Active</span>
                    @else
                    <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-600">Inactive</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="font-semibold text-moss hover:underline">Edit</a>

                        @if ($member->is_active)
                        <form method="POST" action="{{ route('admin.staff.destroy', $member) }}"
                            data-confirm="Deactivate {{ $member->name }}? They will no longer be able to log in."
                            data-confirm-title="Deactivate staff account"
                            data-confirm-label="Deactivate"
                            data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <button class="font-semibold text-red-600 hover:underline">Deactivate</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="!py-8 text-center text-gray-500">No staff found.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $staff->links() }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
