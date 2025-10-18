<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use App\Models\Shop;

layout('components.layouts.app');
title('Liste des boutiques');

state(['showModal' => false]);
state(['editingBoutique' => null]);
state(['name' => '', 'address' => '', 'phone' => '', 'email' => '']);

$shops = computed(function () {
    $query = Shop::query();
    $user = auth()->user();

    if ($user && $user->hasRole('vendeur')) {
        // A seller should only see their assigned shop
        $query->where('id', $user->shop_id);
    }

    return $query->paginate(10);
});

$create = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->reset(['name', 'address', 'phone', 'email', 'editingBoutique']);
    $this->showModal = true;
};

$edit = function (Shop $shop) {
    if (! auth()->user()->hasRole('admin')) return;
    $this->editingBoutique = $shop;
    $this->name = $shop->name;
    $this->address = $shop->address;
    $this->phone = $shop->phone;
    $this->email = $shop->email;
    $this->showModal = true;
};

$save = function () {
    $this->validate([
        'name' => 'required|string|max:255',
        'address' => 'nullable|string|max:255',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
    ]);

    if ($this->editingBoutique) {
        if (! auth()->user()->hasRole('admin')) return;
        $this->editingBoutique->update([
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);

        $this->showModal = false;
        $this->reset(['name', 'address', 'phone', 'email', 'editingBoutique']);
    } else {
        if (! auth()->user()->hasRole('admin')) return;
        $shop = Shop::query()->create([
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);

        session()->flash('message', 'Boutique créée. Veuillez ajouter des produits et des coiffeurs.');
        return redirect()->route('shops.show', $shop);
    }
};

$delete = function (Shop $shop) {
    if (auth()->user()->hasRole('admin')) {
        $shop->delete();
    }
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Liste des boutiques</h1>
            @role('admin')
            <flux:button wire:click="create">Ajouter une Boutique</flux:button>
            @endrole
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($this->shops as $shop)
            <flux:card class="relative p-4 shadow cursor-pointer hover:ring-1 hover:ring-primary-200 transition" wire:key="{{ $shop->id }}" onclick="window.location.href='{{ route('shops.show', $shop) }}'">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <flux:link :href="route('shops.show', $shop)" wire:navigate class="block">
                            <h3 class="text-lg font-semibold hover:underline">{{ $shop->name }}</h3>
                        </flux:link>
                        <div class="mt-5 space-y-1">
                            <p class="text-sm text-gray-600">Adresse: <span class="font-medium">{{ $shop->address ?? 'N/D' }}</span></p>
                            <p class="text-sm text-gray-600">Téléphone: <span class="font-medium">{{ $shop->phone ?? 'N/D' }}</span></p>
                            <p class="text-sm text-gray-600">E-mail: <span class="font-medium">{{ $shop->email ?? 'N/D' }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <flux:link :href="route('shops.show', $shop)" wire:navigate>
                        <flux:button size="sm">Voir détails</flux:button>
                    </flux:link>
                    @role('admin')
                        <flux:button size="sm" variant="outline" wire:click.stop="edit({{ $shop->id }})">Modifier</flux:button>
                        <flux:button size="sm" variant="danger" wire:click.stop="delete({{ $shop->id }})">Supprimer</flux:button>
                    @endrole
                </div>
            </flux:card>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $this->shops->links() }}
    </div>

    <!-- Modal -->
    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save">
                <h3>{{ $editingBoutique ? 'Modifier' : 'Créer' }} Boutique</h3>

                <div class="space-y-4">
                    <div>
                        <flux:label>Nom</flux:label>
                        <flux:input wire:model="name" required />
                        @error('name') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Adresse</flux:label>
                        <flux:input wire:model="address" />
                        @error('address') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Téléphone</flux:label>
                        <flux:input wire:model="phone" />
                        @error('phone') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>E-mail</flux:label>
                        <flux:input wire:model="email" type="email" />
                        @error('email') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>

                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer</flux:button>
        </form>
    </flux:modal>
</div>
