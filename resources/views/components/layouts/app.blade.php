<x-layouts.app.header :title="$title ?? null">
    <flux:main>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.header>
