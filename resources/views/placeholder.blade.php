<x-dynamic-component :component="$layout" :title="$title">
    <x-ui.empty-state
        icon="construction"
        title="Coming soon"
        description="This page is part of the base setup. Its features will be built in a later stage."
        :class="$layout === 'pos-layout' ? 'm-6' : ''" />
</x-dynamic-component>
