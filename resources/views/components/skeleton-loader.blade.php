@props(['lines' => 3, 'height' => 'h-4'])

<div class="animate-pulse space-y-3" role="status" aria-label="Loading...">
    @for ($i = 0; $i < $lines; $i++)
        <div class="bg-gray-200 rounded {{ $height }} {{ $i === $lines - 1 ? 'w-3/4' : 'w-full' }}"></div>
    @endfor
    <span class="sr-only">Loading...</span>
</div>
