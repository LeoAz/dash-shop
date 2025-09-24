<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use App\Models\Product;
use App\Models\Shop;

layout('components.layouts.app');
title('Liste des produits');

state(['showModal' => false]);
state(['editingProduct' => null]);
state(['shop_id' => '', 'name' => '', 'description' => '', 'price' => '', 'sku' => '']);

$products = computed(fn () => Product::with('shop')->paginate(10));
$selectedBoutique = state('selectedBoutique');

$create = function () {
    $this->reset(['shop_id', 'name', 'description', 'price', 'sku', 'editingProduct']);
    if ($this->selectedBoutique) {
        $this->shop_id = $this->selectedBoutique;
    }
    $this->showModal = true;
};

$edit = function (Product $product) {
    $this->editingProduct = $product;
    $this->shop_id = $product->shop_id;
    $this->name = $product->name;
    $this->description = $product->description;
    $this->price = $product->price;
    $this->sku = $product->sku;
    $this->showModal = true;
};

$save = function () {
    $this->validate([
        'shop_id' => 'required|exists:shops,id',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'sku' => 'required|string|max:50|unique:products,sku' . ($this->editingProduct ? ',' . $this->editingProduct->id : ''),
    ]);

    if ($this->editingProduct) {
        $this->editingProduct->update([
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'sku' => $this->sku,
        ]);
    } else {
        Product::query()->create([
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'sku' => $this->sku,
        ]);
    }

    $this->showModal = false;
    $this->reset(['shop_id', 'name', 'description', 'price', 'sku', 'editingProduct']);
};

$delete = function (Product $product) {
    if (auth()->user()->hasRole('admin')) {
        $product->delete();
    }
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Produits</h1>
            <flux:button wire:click="create">Ajouter un produit</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nom</flux:table.column>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Boutique</flux:table.column>
            <flux:table.column>Prix</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->products as $product)
                @if(!$selectedBoutique || $product->shop_id == $selectedBoutique)
                    <flux:table.row wire:key="{{ $product->id }}">
                        <flux:table.cell>{{ $product->name }}</flux:table.cell>
                        <flux:table.cell>{{ $product->sku }}</flux:table.cell>
                        <flux:table.cell>{{ $product->shop->name }}</flux:table.cell>
                        <flux:table.cell>${{ number_format($product->price, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" wire:click="edit({{ $product->id }})">Modifier</flux:button>
                            @if(auth()->user()->hasRole('admin'))
                                <flux:button variant="ghost" size="sm" wire:click="delete({{ $product->id }})">Supprimer</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->products->links() }}
    </div>

    <!-- Modal -->
    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save">
                <h3>{{ $editingProduct ? 'Modifier' : 'Créer' }} un produit</h3>

                <div class="space-y-4">
                    <div>
                        <flux:label>Boutique</flux:label>
                        <flux:select wire:model="shop_id" required>
                            <option value="">Sélectionnez une boutique</option>
                            @foreach(\App\Models\Shop::all() as $boutique)
                                <option value="{{ $boutique->id }}">{{ $boutique->name }}</option>
                            @endforeach
                        </flux:select>
                        @error('shop_id') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Nom</flux:label>
                        <flux:input wire:model="name" required />
                        @error('name') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Description</flux:label>
                        <flux:textarea wire:model="description" />
                        @error('description') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Prix</flux:label>
                        <flux:input wire:model="price" type="number" step="0.01" required />
                        @error('price') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>SKU</flux:label>
                        <flux:input wire:model="sku" required />
                        @error('sku') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer</flux:button>
        </form>
    </flux:modal>
</div>
