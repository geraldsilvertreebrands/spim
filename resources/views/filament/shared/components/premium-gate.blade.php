@if($hasPremiumAccess)
    {{-- User has premium access - show full content --}}
    {{ $slot }}
@else
    {{-- User doesn't have premium access - show locked placeholder --}}
    <x-premium-locked-placeholder :feature="$feature" />
@endif
