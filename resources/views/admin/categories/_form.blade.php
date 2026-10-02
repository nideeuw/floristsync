@php
$category = $category ?? null;
$active = count(old()) ? (bool) old('is_active') : ($category->is_active ?? true);
@endphp

<div class="space-y-4">
    <div>
        <label for="category_name" class="mb-1 block text-sm font-medium">Name</label>
        <input id="category_name" name="category_name" type="text" required class="input"
            value="{{ old('category_name', $category?->category_name) }}">
    </div>

    <div>
        <label for="description" class="mb-1 block text-sm font-medium">Description</label>
        <textarea id="description" name="description" rows="3" class="input">{{ old('description', $category?->description) }}</textarea>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="accent-moss" @checked($active)>
        Active
    </label>
</div>
