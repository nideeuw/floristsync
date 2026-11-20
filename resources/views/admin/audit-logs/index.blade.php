<x-staff-layout title="Audit log">
    <form method="GET" class="card mb-6 grid gap-3 p-5 text-sm sm:grid-cols-5">
        <select name="user_id" class="input">
            <option value="">All users</option>
            @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <select name="action" class="input">
            <option value="">All actions</option>
            @foreach ($actions as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
        <div class="flex gap-2">
            <button class="btn-primary flex-1">Filter</button>
            <a href="{{ route('admin.audit-logs') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="bg-blush/40 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-5 py-3">Time</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Source</th>
                        <th class="px-5 py-3">Action</th>
                        <th class="px-5 py-3">Record</th>
                        <th class="px-5 py-3">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blush">
                    @forelse ($logs as $log)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-5 py-3">{{ $log->created_on?->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-3">{{ $log->user?->name ?? '-' }}</td>
                        <td class="px-5 py-3">{{ $log->source }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $log->action }}</td>
                        <td class="px-5 py-3">{{ $log->entity }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                        <td class="px-5 py-3 text-xs text-gray-700">
                            @foreach ($log->changeLines() as $line)
                            <div>{{ $line }}</div>
                            @endforeach
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-gray-500">No audit entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-staff-layout>
