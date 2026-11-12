@props(['type' => 'success'])
@php
    $styles = [
        'success' => ['wrap' => 'border-cvsu-green-300 bg-cvsu-green-50', 'icon' => 'bxs-check-circle text-cvsu-green-600', 'text' => 'text-cvsu-green-800', 'close' => 'text-cvsu-green-700 hover:text-cvsu-green-900'],
        'error'   => ['wrap' => 'border-red-300 bg-red-50', 'icon' => 'bxs-error-circle text-danger', 'text' => 'text-red-800', 'close' => 'text-red-700 hover:text-red-900'],
    ][$type];
@endphp
<div {{ $attributes->class(['ui-alert mb-5', $styles['wrap']]) }} role="alert">
    <i class='bx {{ $styles['icon'] }} mt-0.5 text-xl'></i>
    <p class="{{ $styles['text'] }}">{{ $slot }}</p>
    <button type="button" class="ml-auto {{ $styles['close'] }}" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
</div>
