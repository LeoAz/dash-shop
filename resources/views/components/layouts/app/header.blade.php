<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ms-2 me-5 flex items-center space-x-2 rtl:space-x-reverse lg:ms-0" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                @role('admin|vendeur')
                <flux:navbar.item icon="building-storefront" :href="route('shops')" :current="request()->routeIs('shops') || request()->routeIs('shops.*')" wire:navigate>
                    {{ __('Boutiques') }}
                </flux:navbar.item>
                @endrole

                @role('admin')
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Tableau de bord') }}
                </flux:navbar.item>
                <flux:navbar.item icon="tag" :href="route('products')" :current="request()->routeIs('products')" wire:navigate>
                    {{ __('Produits') }}
                </flux:navbar.item>
                <flux:navbar.item icon="banknotes" :href="route('sales')" :current="request()->routeIs('sales')" wire:navigate>
                    {{ __('Ventes') }}
                </flux:navbar.item>
                <flux:navbar.item icon="users" :href="route('users')" :current="request()->routeIs('users')" wire:navigate>
                    {{ __('Utilisateurs') }}
                </flux:navbar.item>
                <flux:navbar.item icon="shield-check" :href="route('roles')" :current="request()->routeIs('roles')" wire:navigate>
                    {{ __('Rôles') }}
                </flux:navbar.item>
                @endrole
            </flux:navbar>

            <flux:spacer />


            <!-- Desktop User Menu -->
            <flux:dropdown position="top" align="end">
                <flux:profile
                    class="cursor-pointer"
                    :initials="auth()->user()->initials()"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Paramètres') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Déconnexion') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar stashable sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="ms-1 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Plateforme')">
                    @role('vendeur')
                    <flux:navlist.item icon="building-storefront" :href="route('shops')" :current="request()->routeIs('shops') || request()->routeIs('shops.*')" wire:navigate>
                    {{ __('Boutiques') }}
                    </flux:navlist.item>
                    @endrole

                    @role('admin')
                    <flux:navlist.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Tableau de bord') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="tag" :href="route('products')" :current="request()->routeIs('products')" wire:navigate>
                    {{ __('Produits') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('sales')" :current="request()->routeIs('sales')" wire:navigate>
                    {{ __('Ventes') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('users')" :current="request()->routeIs('users')" wire:navigate>
                    {{ __('Utilisateurs') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="shield-check" :href="route('roles')" :current="request()->routeIs('roles')" wire:navigate>
                    {{ __('Rôles') }}
                    </flux:navlist.item>
                    @endrole
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
            </flux:navlist>
        </flux:sidebar>

        {{ $slot }}

        <ui-toast id="app-toast" position="top end" popover>
            <template>
                <div data-flux-toast-dialog class="rounded-lg bg-white shadow-lg ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700 p-4">
                    <div class="text-sm font-semibold mb-1"><slot name="title">Notification</slot></div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-300"><slot name="description"></slot></div>
                </div>
            </template>
        </ui-toast>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const toastEl = document.getElementById('app-toast');
                function showToast({ title = '', description = '', variant = 'info', duration = 5000 } = {}) {
                    if (!toastEl || typeof toastEl.showToast !== 'function') return;
                    toastEl.showToast({
                        slots: { title, description },
                        dataset: { variant },
                        duration: duration,
                    });
                }
                // Listen to Livewire dispatched events (bubble to window)
                window.addEventListener('toast', (e) => showToast(e.detail || {}));
                // Fallback for Livewire helper if available
                if (window.Livewire && typeof Livewire.on === 'function') {
                    Livewire.on('toast', (payload) => showToast(payload || {}));
                }
            });
        </script>

        @fluxScripts
    </body>
</html>
