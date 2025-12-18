<div class="relative" x-data="{ open: @entangle('isOpen') }">
    {{-- Notification Bell Button --}}
    <button
        type="button"
        @click="open = !open"
        class="relative p-2 rounded-full text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        aria-label="Notifications"
    >
        <x-heroicon-o-bell class="w-5 h-5" />

        {{-- Unread Badge --}}
        @if($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-danger-500 text-[10px] font-medium text-white ring-2 ring-white dark:ring-gray-900">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        @click.outside="open = false"
        class="absolute right-0 z-50 mt-2 w-80 sm:w-96 origin-top-right rounded-xl bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700"
        style="display: none;"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                Price Alerts
                @if($this->unreadCount > 0)
                    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">({{ $this->unreadCount }} unread)</span>
                @endif
            </h3>
            @if($this->unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium"
                >
                    Mark all read
                </button>
            @endif
        </div>

        {{-- Notifications List --}}
        <div class="max-h-96 overflow-y-auto">
            @forelse($this->notifications as $notification)
                <div
                    class="flex items-start gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ !$notification['read'] ? 'bg-primary-50/50 dark:bg-primary-900/10' : '' }}"
                    wire:key="notification-{{ $notification['id'] }}"
                >
                    {{-- Icon --}}
                    <div @class([
                        'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center',
                        'bg-success-100 text-success-600 dark:bg-success-900/50 dark:text-success-400' => $notification['color'] === 'success',
                        'bg-danger-100 text-danger-600 dark:bg-danger-900/50 dark:text-danger-400' => $notification['color'] === 'danger',
                        'bg-warning-100 text-warning-600 dark:bg-warning-900/50 dark:text-warning-400' => $notification['color'] === 'warning',
                        'bg-info-100 text-info-600 dark:bg-info-900/50 dark:text-info-400' => $notification['color'] === 'info',
                        'bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400' => $notification['color'] === 'primary',
                    ])>
                        @if($notification['icon'] === 'heroicon-o-arrow-trending-down')
                            <x-heroicon-o-arrow-trending-down class="w-4 h-4" />
                        @elseif($notification['icon'] === 'heroicon-o-exclamation-triangle')
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4" />
                        @elseif($notification['icon'] === 'heroicon-o-currency-dollar')
                            <x-heroicon-o-currency-dollar class="w-4 h-4" />
                        @elseif($notification['icon'] === 'heroicon-o-x-circle')
                            <x-heroicon-o-x-circle class="w-4 h-4" />
                        @else
                            <x-heroicon-o-bell class="w-4 h-4" />
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $notification['title'] }}
                            </p>
                            @if(!$notification['read'])
                                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-primary-500"></span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                            {{ $notification['message'] }}
                        </p>
                        @if($notification['competitor_name'] && $notification['competitor_price'])
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                {{ $notification['competitor_name'] }}: R{{ number_format($notification['competitor_price'], 2) }}
                            </p>
                        @endif
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $notification['created_at'] }}
                            </span>
                            @if(!$notification['read'])
                                <button
                                    type="button"
                                    wire:click="markAsRead('{{ $notification['id'] }}')"
                                    class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    Mark read
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Delete Button --}}
                    <button
                        type="button"
                        wire:click="deleteNotification('{{ $notification['id'] }}')"
                        class="flex-shrink-0 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Delete notification"
                    >
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <x-heroicon-o-bell-slash class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No notifications</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">Price alert notifications will appear here</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if(count($this->notifications) > 0)
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
                <a
                    href="{{ url('/pricing') }}"
                    class="block text-center text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium"
                >
                    View all alerts
                </a>
            </div>
        @endif
    </div>
</div>
