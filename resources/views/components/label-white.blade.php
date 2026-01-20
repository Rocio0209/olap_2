@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-md text-white font-institucional-sb']) }}>
    {{ $value ?? $slot }}
</label>
