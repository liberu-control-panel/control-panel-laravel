@props(['disabled' => false])

@php
$hasError = false;
if (isset($errors) && method_exists($errors, 'has') && $attributes->has('name')) {
    $hasError = $errors->has($attributes->get('name'));
}
@endphp

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 focus:border-blue-500 text-gray-700 focus:ring-blue-500 rounded-md shadow-sm transition-colors duration-150 ' . ($hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '')]) !!}>
