@props(['name', 'fallback' => 'circle'])

@php
$class = $attributes->get('class', '');
$extra = array_merge(['aria-hidden' => 'true'], $attributes->except('class')->getAttributes());

try {
$svg = svg('lucide-' . $name, $class, $extra);
} catch (\BladeUI\Icons\Exceptions\SvgNotFound) {
$svg = svg('lucide-' . $fallback, $class, $extra);
}
@endphp

{{ $svg }}
