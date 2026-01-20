@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link activaMenu'
            : 'nav-link ';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
