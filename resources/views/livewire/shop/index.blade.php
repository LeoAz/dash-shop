<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use App\Models\Shop;

layout('components.layouts.app');
title('Liste des boutiques / Salon de coiffure');

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
        <h1 class="text-2xl font-bold">Liste des boutiques / Salon de coiffure</h1>
            @role('admin')
            <flux:button wire:click="create">Ajouter une Boutique</flux:button>
            @endrole
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nom</flux:table.column>
            <flux:table.column>Adresse</flux:table.column>
            <flux:table.column>Téléphone</flux:table.column>
            <flux:table.column>E-mail</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->shops as $shop)
                <flux:table.row wire:key="{{ $shop->id }}">
                    <flux:table.cell>{{ $shop->name }}</flux:table.cell>
                    <flux:table.cell>{{ $shop->address ?? 'N/D' }}</flux:table.cell>
                    <flux:table.cell>{{ $shop->phone ?? 'N/D' }}</flux:table.cell>
                    <flux:table.cell>{{ $shop->email ?? 'N/D' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('shops.show', $shop)" wire:navigate>
                            <flux:button variant="ghost" size="sm">Details de la boutique</flux:button>
                        </flux:link>
                        @role('admin')
                        <flux:button variant="ghost" size="sm" wire:click="edit({{ $shop->id }})">Modifier</flux:button>
                        @endrole
                        @if(auth()->user()->hasRole('admin'))
                            <flux:button variant="ghost" size="sm" wire:click="delete({{ $shop->id }})">Supprimer</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

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
