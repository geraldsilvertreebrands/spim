<x-filament-panels::page>
    {{-- Hero Section --}}
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 mb-3">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
            </svg>
        </div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
            Unlock Premium Analytics
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Get deeper insights into your brand performance with our premium analytics suite.
        </p>
    </div>

    {{-- Features Grid --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-6">
        @foreach($features as $feature)
            <div class="relative group">
                {{-- Feature Card --}}
                <div class="h-full bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 transition-all duration-200 hover:shadow-md hover:border-amber-300 dark:hover:border-amber-600">
                    {{-- Premium Badge --}}
                    <div class="absolute -top-2 -right-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gradient-to-r from-amber-400 to-amber-600 text-white shadow-sm">
                            Premium
                        </span>
                    </div>

                    {{-- Icon --}}
                    <div class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 mb-2">
                        <x-dynamic-component :component="$feature['icon']" class="w-3 h-3" />
                    </div>

                    {{-- Content --}}
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                        {{ $feature['name'] }}
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        {{ $feature['description'] }}
                    </p>

                    {{-- Blur overlay effect --}}
                    <div class="mt-3 h-12 bg-gradient-to-b from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 rounded relative overflow-hidden">
                        {{-- Simulated blurred chart --}}
                        <div class="filter blur-sm p-1">
                            <div class="flex items-end gap-0.5 h-10">
                                @for($i = 0; $i < 8; $i++)
                                    <div class="flex-1 bg-amber-400 dark:bg-amber-600 rounded-t" style="height: {{ rand(20, 100) }}%"></div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- CTA Section --}}
    <x-filament::section>
        <div class="text-center py-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">
                Ready to upgrade?
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 max-w-xl mx-auto">
                Contact our team to learn more about premium features.
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                {{-- Email CTA --}}
                <a href="mailto:{{ $this->getContactEmail() }}?subject=Premium%20Analytics%20Inquiry"
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-amber-500 to-amber-600 rounded-lg hover:from-amber-600 hover:to-amber-700 transition-all duration-200 shadow hover:shadow-md">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Contact Sales
                </a>

                {{-- Phone CTA --}}
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $this->getContactPhone()) }}"
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    {{ $this->getContactPhone() }}
                </a>
            </div>

            {{-- Contact Info --}}
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Or email us at
                <a href="mailto:{{ $this->getContactEmail() }}" class="text-amber-600 dark:text-amber-400 hover:underline">
                    {{ $this->getContactEmail() }}
                </a>
            </p>
        </div>
    </x-filament::section>

    {{-- Benefits List --}}
    <div class="mt-6 grid gap-3 md:grid-cols-3">
        <div class="flex items-start gap-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h4 class="text-sm font-medium text-green-800 dark:text-green-300">Priority Support</h4>
                <p class="text-xs text-green-700 dark:text-green-400">Dedicated account manager</p>
            </div>
        </div>
        <div class="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">Custom Reports</h4>
                <p class="text-xs text-blue-700 dark:text-blue-400">Tailored analytics reports</p>
            </div>
        </div>
        <div class="flex items-start gap-2 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
            <svg class="w-4 h-4 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h4 class="text-sm font-medium text-purple-800 dark:text-purple-300">Early Access</h4>
                <p class="text-xs text-purple-700 dark:text-purple-400">First to see new features</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
