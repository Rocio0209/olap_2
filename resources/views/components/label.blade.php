@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-md font-institucional-sb']) }}>
    {{ $value ?? $slot }}
</label>
