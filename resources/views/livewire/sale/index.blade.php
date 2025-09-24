<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Hairdresser;

layout('components.layouts.app');
title('Ventes');

state(['showModal' => false]);
state(['showAssignModal' => false]);
state(['selectedSale' => null]);
state(['shop_id' => '', 'products' => [], 'total_amount' => 0, 'customer_name' => '']);
state(['availableProducts' => []]);
state(['availableHairdressers' => []]);
state(['hairdresser_id' => '']);
state(['assignments' => []]);
state(['saleHairdresser' => []]);
state(['start_date' => '', 'end_date' => '']);
state(['filter_shop_id' => '']);

$sales = computed(function () {
    $query = Sale::with(['shop', 'user', 'products', 'hairdresser', 'receipt']);

    // Role-based shop scoping
    $user = auth()->user();
    if ($user && $user->hasRole('vendeur')) {
        // Sellers can only see sales from their assigned shop
        $query->where('shop_id', $user->shop_id ?? 0);
    } elseif (! empty($this->filter_shop_id)) {
        // Admin can filter by shop when a filter is selected
        $query->where('shop_id', $this->filter_shop_id);
    }

    // Date filters
    if ($this->start_date && $this->end_date) {
        $query->whereBetween('sale_date', [\Carbon\Carbon::parse($this->start_date)->startOfDay(), \Carbon\Carbon::parse($this->end_date)->endOfDay()]);
    } elseif ($this->start_date) {
        $query->where('sale_date', '>=', \Carbon\Carbon::parse($this->start_date)->startOfDay());
    } elseif ($this->end_date) {
        $query->where('sale_date', '<=', \Carbon\Carbon::parse($this->end_date)->endOfDay());
    } else {
        $query->whereDate('sale_date', today());
    }

    return $query->orderByDesc('sale_date')->paginate(10);
});

mount(function () {
    $this->availableProducts = collect();
    // Auto-detect shop in question
    if (auth()->user()->hairdresser) {
        $this->shop_id = auth()->user()->hairdresser->shop_id;
    } elseif (request()->has('shop_id')) {
        $this->shop_id = (string) request()->integer('shop_id');
    } else {
        // If there's only one shop, preselect it
        if (Shop::count() === 1) {
            $this->shop_id = (string) Shop::value('id');
        }
    }

    if ($this->shop_id) {
        $this->loadProducts();
    }
});

$loadProducts = function () {
    if ($this->shop_id) {
        $this->availableProducts = Product::where('shop_id', $this->shop_id)->get();
        $this->availableHairdressers = Hairdresser::where('shop_id', $this->shop_id)->get();
    }
};

$create = function () {
    // Do not reset preselected shop_id; keep context.
    $this->reset(['products', 'total_amount', 'customer_name', 'hairdresser_id']);
    $this->products = [['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]];
    $this->loadProducts();
    $this->showModal = true;
};

$addProduct = function () {
    $this->products[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0];
};

$removeProduct = function ($index) {
    unset($this->products[$index]);
    $this->products = array_values($this->products);
    $this->calculateTotal();
};

$updateProduct = function ($index) {
    $product = $this->availableProducts->find($this->products[$index]['product_id']);
    if ($product) {
        $this->products[$index]['unit_price'] = $product->price;
        $this->products[$index]['subtotal'] = $product->price * $this->products[$index]['quantity'];
        $this->calculateTotal();
    }
};

$calculateTotal = function () {
    $this->total_amount = collect($this->products)->sum('subtotal');
};

$assignSaleHairdresser = function (Sale $sale) {
    $hairdresserId = $this->saleHairdresser[$sale->id] ?? null;
    if ($hairdresserId) {
        $sale->update([
            'hairdresser_id' => $hairdresserId,
            'status' => 'assigned',
        ]);
        session()->flash('message', 'Vente assignée au coiffeur avec succès !');
    }
};

$save = function () {
    $this->validate([
        'shop_id' => 'required|exists:shops,id',
        'customer_name' => 'required|string|max:255',
        'products' => 'required|array|min:1',
        'products.*.product_id' => 'required|exists:products,id',
        'products.*.quantity' => 'required|integer|min:1',
        'total_amount' => 'required|numeric|min:0',
    ]);

    // Validate stock availability before creating the sale
    foreach ($this->products as $item) {
        $p = Product::find($item['product_id']);
        if ($p && $p->quantity < $item['quantity']) {
            $this->addError('products', 'Stock insuffisant pour le produit : ' . $p->name);
            return;
        }
    }

    $sale = Sale::create([
        'shop_id' => $this->shop_id,
        'user_id' => auth()->id(),
        'customer_name' => $this->customer_name,
        'sale_date' => now(),
        'total_amount' => $this->total_amount,
        'hairdresser_id' => $this->hairdresser_id ?: null,
        'status' => 'pending',
    ]);

    foreach ($this->products as $product) {
        if ($product['product_id']) {
            // Attach product (no inventory reference)
            $sale->products()->attach($product['product_id'], [
                'quantity' => $product['quantity'],
                'unit_price' => $product['unit_price'],
                'subtotal' => $product['subtotal'],
            ]);

            // Decrease product quantity directly
            Product::where('id', $product['product_id'])->decrement('quantity', $product['quantity']);
        }
    }

    $this->showModal = false;
    // Keep shop_id context; reset products and totals and customer_name
    $this->reset(['products', 'total_amount', 'customer_name']);
};

$showAssign = function (Sale $sale) {
    $this->selectedSale = $sale;
    $this->hairdresser_id = $sale->hairdresser_id; // preload sale-level hairdresser into modal

    $this->showAssignModal = true;
};

$saveAssignments = function () {
    if ($this->selectedSale) {
        // Update sale-level hairdresser if provided
        $this->selectedSale->hairdresser_id = $this->hairdresser_id ?: null;
        $this->selectedSale->status = 'assigned';
        $this->selectedSale->save();
    }

    $this->showAssignModal = false;
    $this->reset(['selectedSale']);
};

$completeSale = function (Sale $sale) {
    $sale->update(['status' => 'completed']);
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Ventes</h1>
        <span class="text-sm text-gray-600">Créez des ventes depuis la page d'une boutique.</span>
    </div>

    <div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        @role('admin')
        <div>
            <flux:label>Boutique</flux:label>
            <flux:select wire:model.defer="filter_shop_id">
                <option value="">Toutes les boutiques</option>
                @foreach(\App\Models\Shop::orderBy('name')->get() as $shop)
                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                @endforeach
            </flux:select>
        </div>
        @endrole
        <div>
            <flux:label>Date de début</flux:label>
            <flux:input type="date" wire:model.defer="start_date" />
        </div>
        <div>
            <flux:label>Date de fin</flux:label>
            <flux:input type="date" wire:model.defer="end_date" />
        </div>
        <div class="flex gap-2">
            <flux:button type="button" wire:click="$refresh">Filtrer</flux:button>
            <flux:button type="button" variant="ghost" wire:click="$set('start_date', '{{ now()->toDateString() }}'); $set('end_date', '{{ now()->toDateString() }}')">Aujourd'hui</flux:button>
            <flux:button type="button" variant="ghost" wire:click="$set('start_date', ''); $set('end_date', '')">Effacer</flux:button>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Ticket</flux:table.column>
            <flux:table.column>Boutique</flux:table.column>
            <flux:table.column>Montant total</flux:table.column>
            <flux:table.column>Statut</flux:table.column>
            <flux:table.column>Coiffeur</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->sales as $sale)
                <flux:table.row wire:key="{{ $sale->id }}">
                    <flux:table.cell>{{ $sale->sale_date->format('Y-m-d') }}</flux:table.cell>
                    <flux:table.cell>{{ $sale->receipt?->receipt_number ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $sale->shop->name }}</flux:table.cell>
                    <flux:table.cell>${{ number_format($sale->total_amount, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $sale->status === 'completed' ? 'green' : ($sale->status === 'assigned' ? 'yellow' : 'gray') }}">
                            {{ ucfirst($sale->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $sale->hairdresser?->name ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap items-center gap-2">
                            @if($sale->status === 'pending' && auth()->user()->can('assign sales'))
                                <flux:button variant="ghost" size="sm" wire:click="showAssign({{ $sale->id }})">Assigner</flux:button>
                            @elseif($sale->status === 'assigned' && auth()->user()->can('assign sales'))
                                <flux:button variant="ghost" size="sm" wire:click="completeSale({{ $sale->id }})">Terminer</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->sales->links() }}
    </div>

    <!-- New Sale Modal -->
    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save">
                <h3>Nouvelle vente</h3>
                <div class="space-y-4">
                    @if(!$shop_id)
                        <div>
                            <flux:label>Boutique</flux:label>
                            <flux:select wire:model="shop_id" wire:change="loadProducts" required>
                                <option value="">Sélectionnez une boutique</option>
                                @foreach(\App\Models\Shop::all() as $boutique)
                                    <option value="{{ $boutique->id }}">{{ $boutique->name }}</option>
                                @endforeach
                            </flux:select>
                            @error('shop_id') <flux:error>{{ $message }}</flux:error> @enderror
                        </div>
                    @else
                        <div>
                            <flux:label>Boutique</flux:label>
                            <div class="mt-1 text-sm">{{ optional(\App\Models\Shop::find($shop_id))->name }}</div>
                        </div>
                    @endif

                    <div>
                        <flux:label>Client</flux:label>
                        <flux:input wire:model="customer_name" type="text" placeholder="Entrez le nom du client" required />
                        @error('customer_name') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>

                    <div>
                        <flux:label>Coiffeur (optionnel)</flux:label>
                        <flux:select wire:model="hairdresser_id">
                            <option value="">Sélectionnez un coiffeur</option>
                            @foreach($availableHairdressers as $hairdresser)
                                @if(!$shop_id || $hairdresser->shop_id == $shop_id)
                                    <option value="{{ $hairdresser->id }}">{{ $hairdresser->name }}</option>
                                @endif
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label>Produits</flux:label>
                            <flux:button type="button" size="sm" wire:click="addProduct">Ajouter un produit</flux:button>
                        </div>

                        @foreach($products as $index => $product)
                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-5">
                                    <flux:select wire:model="products.{{ $index }}.product_id" wire:change="updateProduct({{ $index }})" required>
                                        <option value="">Sélectionnez un produit</option>
                                        @foreach($availableProducts as $availableProduct)
                                            <option value="{{ $availableProduct->id }}">{{ $availableProduct->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <div class="col-span-2">
                                    <flux:input wire:model="products.{{ $index }}.quantity" type="number" min="1" wire:change="updateProduct({{ $index }})" required />
                                </div>
                                <div class="col-span-3">
                                    <flux:input wire:model="products.{{ $index }}.unit_price" type="number" step="0.01" readonly />
                                </div>
                                <div class="col-span-2">
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="removeProduct({{ $index }})">Retirer</flux:button>
                                </div>
                            </div>
                        @endforeach

                        <div class="text-right mt-2">
                            <strong>Total : ${{ number_format($total_amount, 2) }}</strong>
                        </div>
                    </div>
                </div>
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer la vente</flux:button>
        </form>
    </flux:modal>

    <!-- Modal d'attribution des coiffeurs -->
    <flux:modal wire:model="showAssignModal">
        <form wire:submit.prevent="saveAssignments">
                <h3>Assigner aux coiffeurs</h3>

                <div class="space-y-4">
                    <div>
                        <flux:label>Coiffeur de la vente</flux:label>
                        <flux:select wire:model="hairdresser_id">
                            <option value="">Sélectionnez un coiffeur</option>
                            @foreach(\App\Models\Hairdresser::where('shop_id', $selectedSale?->shop_id)->get() as $hairdresser)
                                <option value="{{ $hairdresser->id }}">{{ $hairdresser->name }}</option>
                            @endforeach
                        </flux:select>
                        <small class="text-gray-500">Optionnel : Assigner un coiffeur principal pour cette vente.</small>
                    </div>

                </div>
                <flux:button type="button" variant="ghost" wire:click="$set('showAssignModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer les attributions</flux:button>
        </form>
    </flux:modal>
</div>
