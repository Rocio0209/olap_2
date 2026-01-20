@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-md text-black font-institucional-sb']) }}>
    {{ $value ?? $slot }}
</label>
