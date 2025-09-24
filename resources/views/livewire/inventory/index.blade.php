<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use App\Models\Inventory;

layout('components.layouts.app');
title('Inventory Management');

state(['showModal' => false]);
state(['editingInventory' => null]);
state(['quantity' => '', 'minimum_stock' => '']);
state(['selectedBoutique' => '']);

$inventories = computed(function () {
    $query = Inventory::with(['shop', 'product']);

    if ($this->selectedBoutique) {
        $query->where('shop_id', $this->selectedBoutique);
    }

    return $query->paginate(15);
});

$edit = function (Inventory $inventory) {
    $this->editingInventory = $inventory;
    $this->quantity = $inventory->quantity;
    $this->minimum_stock = $inventory->minimum_stock;
    $this->showModal = true;
};

$save = function () {
    $this->validate([
        'quantity' => 'required|integer|min:0',
        'minimum_stock' => 'required|integer|min:0',
    ]);

    $this->editingInventory->update([
        'quantity' => $this->quantity,
        'minimum_stock' => $this->minimum_stock,
    ]);

    $this->showModal = false;
    $this->reset(['quantity', 'minimum_stock', 'editingInventory']);
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Inventory Management</h1>
    </div>

    <div class="mb-4">
        <flux:select wire:model="selectedBoutique" placeholder="Filter by boutique">
            <option value="">All Boutiques</option>
            @foreach(\App\Models\Shop::all() as $boutique)
                <option value="{{ $boutique->id }}">{{ $boutique->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Boutique</flux:table.column>
            <flux:table.column>Product</flux:table.column>
            <flux:table.column>Quantity</flux:table.column>
            <flux:table.column>Minimum Stock</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->inventories as $inventory)
                <flux:table.row wire:key="{{ $inventory->id }}">
                    <flux:table.cell>{{ $inventory->shop->name }}</flux:table.cell>
                    <flux:table.cell>{{ $inventory->product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $inventory->quantity }}</flux:table.cell>
                    <flux:table.cell>{{ $inventory->minimum_stock }}</flux:table.cell>
                    <flux:table.cell>
                        @if($inventory->isLowStock())
                            <flux:badge color="red">Low Stock</flux:badge>
                        @else
                            <flux:badge color="green">In Stock</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('manage inventory')
                            <flux:button variant="ghost" size="sm" wire:click="edit({{ $inventory->id }})">Edit</flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->inventories->links() }}
    </div>

    <!-- Edit Modal -->
    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save">
                <h3>Edit Inventory</h3>
                <div class="space-y-4">
                    <div>
                        <flux:label>Product</flux:label>
                        <p class="font-medium">{{ $editingInventory?->product->name }}</p>
                    </div>

                    <div>
                        <flux:label>Boutique</flux:label>
                        <p class="font-medium">{{ $editingInventory?->shop->name }}</p>
                    </div>

                    <div>
                        <flux:label>Current Quantity</flux:label>
                        <flux:input wire:model="quantity" type="number" min="0" required />
                        @error('quantity') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Minimum Stock Level</flux:label>
                        <flux:input wire:model="minimum_stock" type="number" min="0" required />
                        @error('minimum_stock') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit">Save Changes</flux:button>
        </form>
    </flux:modal>
</div>
