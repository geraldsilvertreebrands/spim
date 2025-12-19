@if(count($sections) > 1)
<nav class="sticky top-16 z-40 -mx-4 px-4 py-2 mb-4 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
            Jump to:
        </span>
        <div class="flex items-center gap-1">
            @foreach($sections as $section)
                <a
                    href="#section-{{ $section['id'] }}"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full transition-colors whitespace-nowrap
                           text-gray-600 dark:text-gray-300
                           hover:text-primary-600 dark:hover:text-primary-400
                           hover:bg-primary-50 dark:hover:bg-primary-900/30
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    onclick="event.preventDefault(); document.getElementById('section-{{ $section['id'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                >
                    {{ $section['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
@endif
