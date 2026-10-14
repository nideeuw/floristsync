@php($editing = isset($staff))

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $staff->name ?? '') }}" required maxlength="255" class="input">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="mb-1 block text-sm font-medium">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" required maxlength="255" class="input">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phone" class="mb-1 block text-sm font-medium">Phone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $staff->phone ?? '') }}" maxlength="30" class="input">
        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="role_id" class="mb-1 block text-sm font-medium">Role</label>
        <select id="role_id" name="role_id" required class="input">
            <option value="">Choose a role</option>
            @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected((string) old('role_id', $staff->role_id ?? '') === (string) $role->id)>
                {{ \Illuminate\Support\Str::headline($role->role_name) }}
            </option>
            @endforeach
        </select>
        @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="mb-1 block text-sm font-medium">
            {{ $editing ? 'New password' : 'Password' }}
        </label>
        <input id="password" type="password" name="password" autocomplete="new-password" @required(! $editing) class="input">
        @if ($editing)
        <p class="mt-1 text-xs text-gray-500">Leave empty to keep the current password.</p>
        @endif
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required(! $editing) class="input">
    </div>

    @if ($editing)
    <div>
        <label for="is_active" class="mb-1 block text-sm font-medium">Account status</label>
        <select id="is_active" name="is_active" class="input">
            <option value="1" @selected((string) old('is_active', (int) $staff->is_active) === '1')>Active</option>
            <option value="0" @selected((string) old('is_active', (int) $staff->is_active) === '0')>Inactive (cannot log in)</option>
        </select>
        @error('is_active') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    @endif
</div>
