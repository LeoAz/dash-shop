<?php

use function Livewire\Volt\{layout, title, state, computed, mount};
use App\Models\Shop;
use App\Models\Product;
use App\Models\Hairdresser;
use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;

layout('components.layouts.app');
title('Détails de la boutique');

state(['shop' => null]);

// Tabs state
state(['tab' => 'sales']);

// Promotion state
state(['promo_showModal' => false]);
state(['promo_editing' => null]);
state(['promo_name' => '', 'promo_type' => 'percentage', 'promo_percentage' => 0, 'promo_days' => [], 'promo_starts_at' => null, 'promo_ends_at' => null]);

// Reports filter state
state(['r_from' => null, 'r_to' => null]);

// Product state
state(['p_showModal' => false]);
state(['p_editing' => null]);
state(['p_name' => '', 'p_description' => '', 'p_price' => '', 'p_sku' => '', 'p_quantity' => 0]);
// Product deletion confirmation state
state(['p_showDeleteModal' => false]);
state(['p_deleteId' => null]);

// Hairdresser state
state(['h_showModal' => false]);
state(['h_editing' => null]);
state(['h_name' => '', 'h_phone' => '', 'h_specialty' => '']);

// Sales state
state(['s_showModal' => false]);
state(['s_products' => [], 's_customer_name' => '', 's_total_amount' => 0]);
state(['s_availableProducts' => []]);
state(['s_hairdresser_id' => '']);
state(['s_availableHairdressers' => []]);
state(['s_promotion_id' => '']);
state(['s_availablePromotions' => []]);
state(['s_assignment' => []]);
state(['s_date' => null]);
// Assignment modal state (for sale details)
state(['s_showAssignModal' => false]);
state(['s_selectedSale' => null]);
state(['s_assignments' => []]);

// Receipt state - SIMPLIFIED for controller approach
state(['receiptLoading' => false]);

// Promotion actions
$promo_create = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->reset(['promo_name','promo_type','promo_percentage','promo_days','promo_starts_at','promo_ends_at','promo_editing']);
    $this->promo_type = 'percentage';
    $this->promo_percentage = 0;
    $this->promo_days = [];
    $this->promo_showModal = true;
};

$promo_edit = function (Promotion $promotion) {
    if (! auth()->user()->hasRole('admin')) return;
    $this->promo_editing = $promotion;
    $this->promo_name = $promotion->name;
    $this->promo_type = $promotion->type;
    $this->promo_percentage = (float) $promotion->percentage;
    $this->promo_days = $promotion->days_of_week ?? [];
    $this->promo_starts_at = optional($promotion->starts_at)->toDateString();
    $this->promo_ends_at = optional($promotion->ends_at)->toDateString();
    $this->promo_showModal = true;
};

$promo_save = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->validate([
        'promo_name' => 'required|string|max:255',
        'promo_type' => 'required|in:days,percentage',
        'promo_percentage' => 'required|numeric|min:0|max:100',
        'promo_days' => 'array',
        'promo_days.*' => 'integer|min:0|max:6',
        'promo_starts_at' => 'nullable|date',
        'promo_ends_at' => 'nullable|date|after_or_equal:promo_starts_at',
    ]);

    $data = [
        'shop_id' => $this->shop->id,
        'name' => $this->promo_name,
        'type' => $this->promo_type,
        'percentage' => $this->promo_percentage,
        'days_of_week' => $this->promo_type === 'days' ? array_values($this->promo_days ?? []) : null,
        'starts_at' => $this->promo_starts_at ?: null,
        'ends_at' => $this->promo_ends_at ?: null,
        'active' => true,
    ];

    if ($this->promo_editing) {
        $this->promo_editing->update($data);
    } else {
        Promotion::create($data);
    }

    $this->promo_showModal = false;
    $this->reset(['promo_name','promo_type','promo_percentage','promo_days','promo_starts_at','promo_ends_at','promo_editing']);
};

$promo_toggleActive = function (Promotion $promotion) {
    if (! auth()->user()->hasRole('admin')) return;
    $promotion->active = ! $promotion->active;
    $promotion->save();
};

$promo_delete = function (Promotion $promotion) {
    if (auth()->user()->hasRole('admin')) {
        $promotion->delete();
    }
};

$products = computed(fn () => Product::where('shop_id', $this->shop->id)->latest()->paginate(10));
$hairdressers = computed(fn () => Hairdresser::where('shop_id', $this->shop->id)->latest()->paginate(10));
$promotions = computed(fn () => Promotion::where('shop_id', $this->shop->id)->latest()->get());
$sales = computed(fn () => Sale::where('shop_id', $this->shop->id)
    ->whereDate('sale_date', $this->s_date ?? now()->toDateString())
    ->with(['products', 'user', 'hairdresser', 'receipt', 'promotion'])
    ->latest()
    ->paginate(10));

// Daily sales statistics for the selected date
$salesStats = computed(function () {
    $date = $this->s_date ?? now()->toDateString();

    $baseQuery = Sale::where('shop_id', $this->shop->id)
        ->whereDate('sale_date', $date);

    $totalSales = (clone $baseQuery)->count();
    // Tickets: sales that have an associated receipt
    $tickets = (clone $baseQuery)->whereHas('receipt')->count();

    $perHairdresser = (clone $baseQuery)
        ->selectRaw('hairdresser_id, COUNT(*) as sales_count, SUM(total_amount) as total_amount')
        ->groupBy('hairdresser_id')
        ->get()
        ->map(function ($row) {
            $row->hairdresser_name = optional(\App\Models\Hairdresser::find($row->hairdresser_id))->name;
            return $row;
        });

    return [
        'totalSales' => $totalSales,
        'tickets' => $tickets,
        'perHairdresser' => $perHairdresser,
    ];
});

// Reports computed data (preview tables)
$reportSales = computed(fn () => Sale::where('shop_id', $this->shop->id)
    ->with('hairdresser')
    ->when($this->r_from, fn ($q) => $q->whereDate('sale_date', '>=', $this->r_from))
    ->when($this->r_to, fn ($q) => $q->whereDate('sale_date', '<=', $this->r_to))
    ->latest('sale_date')
    ->take(10)
    ->get());

$reportHairdressers = computed(function () {
    return Sale::selectRaw('hairdresser_id, COUNT(*) as sales_count, SUM(total_amount) as total_amount')
        ->where('shop_id', $this->shop->id)
        ->when($this->r_from, fn ($q) => $q->whereDate('sale_date', '>=', $this->r_from))
        ->when($this->r_to, fn ($q) => $q->whereDate('sale_date', '<=', $this->r_to))
        ->groupBy('hairdresser_id')
        ->orderByDesc(DB::raw('SUM(total_amount)'))
        ->get()
        ->map(function ($row) {
            $row->hairdresser_name = optional(Hairdresser::find($row->hairdresser_id))->name;
            return $row;
        });
});

$reportProducts = computed(function () {
    return ProductSale::selectRaw('product_id, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
        ->whereHas('sale', function ($q) {
            $q->where('shop_id', $this->shop->id)
              ->when($this->r_from, fn ($qq) => $qq->whereDate('sale_date', '>=', $this->r_from))
              ->when($this->r_to, fn ($qq) => $qq->whereDate('sale_date', '<=', $this->r_to));
        })
        ->groupBy('product_id')
        ->orderByDesc(DB::raw('SUM(subtotal)'))
        ->get()
        ->map(function ($row) {
            $product = Product::find($row->product_id);
            $row->product_name = optional($product)->name;
            $row->product_sku = optional($product)->sku;
            return $row;
        });
});

mount(function (Shop $shop) {
    $this->shop = $shop->load(['products', 'hairdressers']);
    $this->s_availableHairdressers = Hairdresser::where('shop_id', $this->shop->id)->get();
    if (! $this->s_date) {
        $this->s_date = now()->toDateString();
    }
});

// Product actions
$p_create = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->reset(['p_name','p_description','p_price','p_sku','p_quantity','p_editing']);
    $this->p_quantity = 0;
    $this->p_showModal = true;
};

$p_edit = function (Product $product) {
    if (! auth()->user()->hasRole('admin')) return;
    $this->p_editing = $product;
    $this->p_name = $product->name;
    $this->p_description = $product->description;
    $this->p_price = $product->price;
    $this->p_sku = $product->sku;
    $this->p_quantity = $product->quantity ?? 0;
    $this->p_showModal = true;
};

$p_generateSku = function () {
    if (! $this->p_sku && $this->p_name) {
        $base = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', strtoupper(str_replace(' ', '-', $this->p_name))));
        $base = trim(preg_replace('/-+/', '-', $base), '-');
        $this->p_sku = $base ? $base . '-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4) : strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
    }
};

$p_save = function () {
    if (! auth()->user()->hasRole('admin')) return;
    // Auto-generate SKU if empty
    if (! $this->p_sku) {
        ($this->p_generateSku)();
    }

    $this->validate([
        'p_name' => 'required|string|max:255',
        'p_description' => 'nullable|string',
        'p_price' => 'required|numeric|min:0',
        'p_sku' => 'required|string|max:50|unique:products,sku' . ($this->p_editing ? ',' . $this->p_editing->id : ''),
        'p_quantity' => 'required|integer|min:0',
    ], [], [
        'p_name' => 'name',
        'p_description' => 'description',
        'p_price' => 'price',
        'p_sku' => 'sku',
        'p_quantity' => 'quantity',
    ]);

    if ($this->p_editing) {
        $this->p_editing->update([
            'name' => $this->p_name,
            'description' => $this->p_description,
            'price' => $this->p_price,
            'sku' => $this->p_sku,
            'quantity' => $this->p_quantity,
        ]);
    } else {
        Product::create([
            'shop_id' => $this->shop->id,
            'name' => $this->p_name,
            'description' => $this->p_description,
            'price' => $this->p_price,
            'sku' => $this->p_sku,
            'quantity' => $this->p_quantity,
        ]);
    }

    $this->p_showModal = false;
    $this->reset(['p_name','p_description','p_price','p_sku','p_quantity','p_editing']);
    session()->flash('message', 'Produit enregistré avec succès !');
};

$p_delete = function (Product $product) {
    if (auth()->user()->hasRole('admin')) {
        $product->delete();
        // Reset confirmation modal state if open
        $this->p_showDeleteModal = false;
        $this->p_deleteId = null;
        session()->flash('message', 'Produit supprimé avec succès !');
    }
};

$p_confirmDelete = function (Product $product) {
    if (! auth()->user()->hasRole('admin')) return;
    $this->p_deleteId = $product->id;
    $this->p_showDeleteModal = true;
};

// Hairdresser actions
$h_create = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->reset(['h_name','h_phone','h_specialty','h_editing']);
    $this->h_showModal = true;
};

$h_edit = function (Hairdresser $hairdresser) {
    if (! auth()->user()->hasRole('admin')) return;
    $this->h_editing = $hairdresser;
    $this->h_name = $hairdresser->name;
    $this->h_phone = $hairdresser->phone;
    $this->h_specialty = $hairdresser->specialty;
    $this->h_showModal = true;
};

$h_save = function () {
    if (! auth()->user()->hasRole('admin')) return;
    $this->validate([
        'h_name' => 'required|string|max:255',
        'h_phone' => 'nullable|string|max:20',
        'h_specialty' => 'nullable|string|max:255',
    ], [], [
        'h_name' => 'name',
        'h_phone' => 'phone',
        'h_specialty' => 'specialty',
    ]);

    if ($this->h_editing) {
        $this->h_editing->update([
            'name' => $this->h_name,
            'phone' => $this->h_phone,
            'specialty' => $this->h_specialty,
        ]);
    } else {
        Hairdresser::create([
            'shop_id' => $this->shop->id,
            'name' => $this->h_name,
            'phone' => $this->h_phone,
            'specialty' => $this->h_specialty,
        ]);
    }

    $this->h_showModal = false;
    $this->reset(['h_name','h_phone','h_specialty','h_editing']);
    session()->flash('message', 'Coiffeur enregistré avec succès !');
};

$h_delete = function (Hairdresser $hairdresser) {
    if (auth()->user()->hasRole('admin')) {
        $hairdresser->delete();
        session()->flash('message', 'Coiffeur supprimé avec succès !');
    }
};

// Sales actions
$s_loadProducts = function () {
    $this->s_availableProducts = Product::where('shop_id', $this->shop->id)->get();
};

$s_loadHairdressers = function () {
    $this->s_availableHairdressers = Hairdresser::where('shop_id', $this->shop->id)->get();
};

$s_loadPromotions = function () {
    $this->s_availablePromotions = Promotion::where('shop_id', $this->shop->id)
        ->where('active', true)
        ->get();
};

$s_create = function () {
    $this->reset(['s_products','s_customer_name','s_total_amount','s_hairdresser_id','s_promotion_id']);
    $this->s_products = [['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]];
    $this->s_loadProducts();
    $this->s_loadHairdressers();
    $this->s_loadPromotions();
    $this->s_showModal = true;
};

$s_addProduct = function () {
    $this->s_products[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0];
};

$s_removeProduct = function ($index) {
    unset($this->s_products[$index]);
    $this->s_products = array_values($this->s_products);
    $this->s_calculateTotal();
};

$s_updateProduct = function ($index) {
    $product = $this->s_availableProducts->find($this->s_products[$index]['product_id']);
    if ($product) {
        $this->s_products[$index]['unit_price'] = $product->price;
        $this->s_products[$index]['subtotal'] = $product->price * $this->s_products[$index]['quantity'];
        $this->s_calculateTotal();
    }
};

$s_calculateTotal = function () {
    $this->s_total_amount = collect($this->s_products)->sum('subtotal');
};

$s_showAssign = function (Sale $sale) {
    $this->s_selectedSale = $sale;
    // Preload sale-level hairdresser
    $this->s_hairdresser_id = $sale->hairdresser_id;

    $this->s_showAssignModal = true;
};

$s_saveAssignments = function () {
    if ($this->s_selectedSale) {
        // Update sale-level hairdresser if provided
        $this->s_selectedSale->hairdresser_id = $this->s_hairdresser_id ?: null;
        $this->s_selectedSale->status = 'assigned';
        $this->s_selectedSale->save();
    }

    $this->s_showAssignModal = false;
    $this->reset(['s_selectedSale']);
};

// Receipt actions - UPDATED FOR CONTROLLER APPROACH
$generateReceipt = function (Sale $sale) {
    // Simply redirect to the receipt print page
    return $this->redirect(route('receipts.print', $sale), navigate: false);
};

$s_save = function () {
    $this->validate([
        's_customer_name' => 'required|string|max:255',
        's_products' => 'required|array|min:1',
        's_products.*.product_id' => 'required|exists:products,id',
        's_products.*.quantity' => 'required|integer|min:1',
        's_total_amount' => 'required|numeric|min:0',
        's_promotion_id' => 'nullable|exists:promotions,id',
    ], [], [
        's_customer_name' => 'customer',
        's_products' => 'products',
    ]);

    // Check stock availability before creating the sale
    foreach ($this->s_products as $item) {
        $p = Product::find($item['product_id']);
        if ($p && $p->quantity < $item['quantity']) {
            $this->addError('s_products', 'Stock insuffisant pour le produit : ' . $p->name);
            return;
        }
    }

    $sale = Sale::create([
        'shop_id' => $this->shop->id,
        'user_id' => auth()->id(),
        'customer_name' => $this->s_customer_name,
        'sale_date' => now(),
        'total_amount' => $this->s_total_amount,
        'hairdresser_id' => $this->s_hairdresser_id ?: null,
        'promotion_id' => $this->s_promotion_id ?: null,
        'status' => 'pending',
    ]);

    // Apply promotion: selected one takes precedence, else active shop promotion (if any)
    $sale->applyPromotion();
    $sale->save();

    foreach ($this->s_products as $product) {
        if ($product['product_id']) {
            $sale->products()->attach($product['product_id'], [
                'quantity' => $product['quantity'],
                'unit_price' => $product['unit_price'],
                'subtotal' => $product['subtotal'],
            ]);

            // Decrease product quantity directly
            Product::where('id', $product['product_id'])->decrement('quantity', $product['quantity']);
        }
    }

    $this->s_showModal = false;
    $this->reset(['s_products','s_customer_name','s_total_amount','s_promotion_id','s_hairdresser_id']);

    // Redirect to auto-print receipt
    return $this->redirect(route('receipts.auto-print', $sale), navigate: false);
};

?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $shop->name }}</h1>
            <p class="text-sm text-gray-600">{{ $shop->address }} · {{ $shop->phone }} · {{ $shop->email }}</p>
            @if (session('message'))
                <p class="mt-2 text-green-600">{{ session('message') }}</p>
            @endif
        </div>
        <div class="space-x-2">
            @role('admin')
            <a href="{{ route('products') }}" class="underline text-sm">Tous les produits</a>
            @endrole
            <a href="{{ route('sales') }}" class="underline text-sm">Ventes</a>
            <a href="{{ route('shops') }}" class="underline text-sm">Retour aux boutiques</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Left Tabs Navigation -->
        <div class="lg:col-span-1">
            <div role="tablist" aria-label="Sections de la boutique" class="flex lg:flex-col gap-2">
                @role('admin')
                <flux:button variant="ghost" class="justify-start w-full {{ $tab === 'products' ? 'bg-muted' : '' }}" wire:click="$set('tab','products')">
                    Liste des Produits
                </flux:button>
                <flux:button variant="ghost" class="justify-start w-full {{ $tab === 'hairdressers' ? 'bg-muted' : '' }}" wire:click="$set('tab','hairdressers')">
                    Liste des Coiffeurs
                </flux:button>
                @endrole

                @role('admin|vendeur')
                <flux:button variant="ghost" class="justify-start w-full {{ $tab === 'sales' ? 'bg-muted' : '' }}" wire:click="$set('tab','sales')">
                    Ventes
                </flux:button>
                @endrole

                @role('admin')
                <flux:button variant="ghost" class="justify-start w-full {{ $tab === 'reports' ? 'bg-muted' : '' }}" wire:click="$set('tab','reports')">
                    Rapports
                </flux:button>
                <flux:button variant="ghost" class="justify-start w-full {{ $tab === 'promotions' ? 'bg-muted' : '' }}" wire:click="$set('tab','promotions')">
                    Promotions
                </flux:button>
                @endrole
            </div>
        </div>

        <!-- Right Panels -->
        <div class="lg:col-span-4">
            @if($tab === 'products')
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold">Produits</h2>
                        @role('admin')
                        <flux:button wire:click="p_create">Ajouter un produit</flux:button>
                        @endrole
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Nom</flux:table.column>
                            <flux:table.column>SKU</flux:table.column>
                            <flux:table.column>Prix</flux:table.column>
                            <flux:table.column>Disponible</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($this->products as $product)
                                <flux:table.row wire:key="p-{{ $product->id }}">
                                    <flux:table.cell>{{ $product->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $product->sku }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($product->price, 2) }}</flux:table.cell>
                                    <flux:table.cell>{{ $product->quantity ?? 0 }}</flux:table.cell>
                                    <flux:table.cell>
                                        @role('admin')
                                        <flux:button variant="ghost" size="sm" wire:click="p_edit({{ $product->id }})">Modifier</flux:button>
                                        <flux:button variant="ghost" size="sm" wire:click="p_confirmDelete({{ $product->id }})">Supprimer</flux:button>
                                        @endrole
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    <div class="mt-2">
                        {{ $this->products->links() }}
                    </div>
                </div>
            @elseif($tab === 'hairdressers')
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold">Coiffeurs</h2>
                        @role('admin')
                        <flux:button wire:click="h_create">Ajouter un coiffeur</flux:button>
                        @endrole
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Nom</flux:table.column>
                            <flux:table.column>Téléphone</flux:table.column>
                            <flux:table.column>Spécialité</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($this->hairdressers as $hairdresser)
                                <flux:table.row wire:key="h-{{ $hairdresser->id }}">
                                    <flux:table.cell>{{ $hairdresser->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $hairdresser->phone }}</flux:table.cell>
                                    <flux:table.cell>{{ $hairdresser->specialty }}</flux:table.cell>
                                    <flux:table.cell>
                                        @role('admin')
                                        <flux:button variant="ghost" size="sm" wire:click="h_edit({{ $hairdresser->id }})">Modifier</flux:button>
                                        <flux:button variant="ghost" size="sm" wire:click="h_delete({{ $hairdresser->id }})">Supprimer</flux:button>
                                        @endrole
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    <div class="mt-2">
                        {{ $this->hairdressers->links() }}
                    </div>
                </div>
            @elseif($tab === 'sales')
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold">Ventes</h2>
                        <div class="flex items-center gap-2">
                            <div>
                                <flux:input type="date" wire:model.live="s_date" />
                            </div>
                            <flux:button size="sm" wire:click="s_create">Ajouter une vente</flux:button>
                            <a class="underline text-sm" href="{{ route('sales') }}">Aller aux ventes</a>
                        </div>
                    </div>

                    <!-- Daily stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div class="p-3 rounded border bg-white">
                            <div class="text-xs text-gray-500">Ventes du jour</div>
                            <div class="text-2xl font-semibold">{{ $this->salesStats['totalSales'] ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded border bg-white">
                            <div class="text-xs text-gray-500">Tickets</div>
                            <div class="text-2xl font-semibold">{{ $this->salesStats['tickets'] ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded border bg-white">
                            <div class="text-xs text-gray-500">Ventes par coiffeur</div>
                            <div class="text-sm mt-1 space-y-1">
                                @forelse(($this->salesStats['perHairdresser'] ?? []) as $row)
                                    <div class="flex justify-between">
                                        <span>{{ $row->hairdresser_name ?? '—' }}</span>
                                        <span class="font-medium">{{ $row->sales_count }}</span>
                                    </div>
                                @empty
                                    <div class="text-gray-500">Aucune donnée</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Ticket</flux:table.column>
                            <flux:table.column>Client</flux:table.column>
                            <flux:table.column>Total</flux:table.column>
                            <flux:table.column>Promotion</flux:table.column>
                            <flux:table.column>Statut</flux:table.column>
                            <flux:table.column>Coiffeur</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($this->sales as $sale)
                                <flux:table.row wire:key="s-{{ $sale->id }}">
                                    <flux:table.cell>{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('Y-m-d H:i') }}</flux:table.cell>
                                    <flux:table.cell>{{ $sale->receipt?->receipt_number ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $sale->customer_name }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($sale->total_amount, 2) }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($sale->promotion)
                                            <div class="text-sm">
                                                <div class="font-medium">{{ $sale->promotion->name }}</div>
                                                @if(!is_null($sale->discount_amount))
                                                    <div class="text-gray-600">-{{ number_format($sale->discount_amount, 2) }}</div>
                                                @endif
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
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
                                            @if($sale->status === 'pending')
                                                <flux:button size="sm" wire:click="s_showAssign({{ $sale->id }})">Attribuer</flux:button>
                                            @endif
                                            <a href="{{ route('receipts.print', $sale) }}" target="_blank" class="text-sm underline ml-2">
                                                Reçu
                                            </a>
                                            <a href="{{ route('receipts.pdf', $sale) }}" target="_blank" class="text-sm underline">
                                                PDF
                                            </a>
                                            <a href="{{ route('receipts.auto-print', $sale) }}" target="_blank" class="text-sm underline">
                                                Imprimer
                                            </a>
                                            <a class="text-sm underline" href="{{ route('sales') }}">Voir</a>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="7">Aucune vente pour le moment.</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>

                    <div class="mt-2">
                        {{ $this->sales->links() }}
                    </div>
                </div>
            @elseif($tab === 'reports')
                <div class="space-y-8">
                    <div class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <flux:label>De</flux:label>
                            <flux:input type="date" wire:model.live="r_from" />
                        </div>
                        <div>
                            <flux:label>À</flux:label>
                            <flux:input type="date" wire:model.live="r_to" />
                        </div>
                        <div class="ml-auto text-sm text-gray-500">
                            Les filtres s’appliquent aux aperçus et aux exports
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-xl font-semibold">Rapport des ventes</h2>
                            <a class="text-sm underline" href="{{ route('reports.sales', $shop) }}?from={{ $this->r_from }}&to={{ $this->r_to }}" target="_blank">Exporter vers Excel</a>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Date') }}</flux:table.column>
                                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                                <flux:table.column>{{ __('coiffeurs') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                                <flux:table.column>{{ __('Total') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->reportSales as $rs)
                                    <flux:table.row>
                                        <flux:table.cell>{{ optional($rs->sale_date)->format('Y-m-d H:i') }}</flux:table.cell>
                                        <flux:table.cell>{{ $rs->customer_name }}</flux:table.cell>
                                        <flux:table.cell>{{ $rs->hairdresser?->name ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ ucfirst($rs->status) }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($rs->total_amount, 2) }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="5">{{ __('No sales found.') }}</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-xl font-semibold">{{ __('Rapports sur les coiffeurs') }}</h2>
                            <a class="text-sm underline" href="{{ route('reports.hairdressers', $shop) }}?from={{ $this->r_from }}&to={{ $this->r_to }}" target="_blank">{{ __('Exporter en  Excel') }}</a>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Coiffeurs') }}</flux:table.column>
                                <flux:table.column>{{ __('Nombre de vente') }}</flux:table.column>
                                <flux:table.column>{{ __('Total') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->reportHairdressers as $rh)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $rh->hairdresser_name ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $rh->sales_count }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($rh->total_amount, 2) }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3">{{ __('No hairdresser data.') }}</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-xl font-semibold">{{ __('Rapports sur les produits') }}</h2>
                            <a class="text-sm underline" href="{{ route('reports.products', $shop) }}?from={{ $this->r_from }}&to={{ $this->r_to }}" target="_blank">{{ __('Export to Excel') }}</a>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Product') }}</flux:table.column>
                                <flux:table.column>{{ __('SKU') }}</flux:table.column>
                                <flux:table.column>{{ __('Quantité total') }}</flux:table.column>
                                <flux:table.column>{{ __('Total revenu') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->reportProducts as $rp)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $rp->product_name ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $rp->product_sku ?? '' }}</flux:table.cell>
                                        <flux:table.cell>{{ $rp->total_qty }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($rp->total_revenue, 2) }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4">{{ __('No product data.') }}</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            @elseif($tab === 'promotions')
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold">Promotions</h2>
                        @role('admin')
                        <flux:button wire:click="promo_create">Ajouter une promotion</flux:button>
                        @endrole
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Nom</flux:table.column>
                            <flux:table.column>Type</flux:table.column>
                            <flux:table.column>Pourcentage</flux:table.column>
                            <flux:table.column>Jours</flux:table.column>
                            <flux:table.column>Actif</flux:table.column>
                            <flux:table.column>Période</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($this->promotions as $promo)
                                <flux:table.row wire:key="promo-{{ $promo->id }}">
                                    <flux:table.cell>{{ $promo->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $promo->type === 'days' ? 'Par jours' : 'Pourcentage global' }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($promo->percentage, 2) }}%</flux:table.cell>
                                    <flux:table.cell>
                                        @if($promo->type === 'days')
                                            @php
                                                $names = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
                                                $list = collect($promo->days_of_week ?? [])->map(fn($d) => $names[(int)$d])->implode(', ');
                                            @endphp
                                            {{ $list ?: '—' }}
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge color="{{ $promo->active ? 'green' : 'gray' }}">{{ $promo->active ? 'Oui' : 'Non' }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($promo->starts_at || $promo->ends_at)
                                            {{ optional($promo->starts_at)->format('Y-m-d') }} – {{ optional($promo->ends_at)->format('Y-m-d') }}
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @role('admin')
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button size="sm" variant="ghost" wire:click="promo_edit({{ $promo->id }})">Modifier</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="promo_toggleActive({{ $promo->id }})">{{ $promo->active ? 'Désactiver' : 'Activer' }}</flux:button>
                                            <flux:button size="sm" variant="danger" wire:click="promo_delete({{ $promo->id }})">Supprimer</flux:button>
                                        </div>
                                        @endrole
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="7">Aucune promotion définie.</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </div>

    <!-- Product Modal -->
    <flux:modal wire:model="p_showModal">
        <form wire:submit.prevent="p_save">
            <h3>{{ $p_editing ? __('Modifier') : __('Créer') }} {{ __('Produit') }}</h3>
            <div class="space-y-4">
                <div>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="p_name" wire:change="p_generateSku" required />
                    @error('p_name') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="p_description" />
                    @error('p_description') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div>
                    <flux:label>{{ __('Prix') }}</flux:label>
                    <flux:input wire:model="p_price" type="number" step="0.01" required />
                    @error('p_price') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:label>{{ __('SKU') }}</flux:label>
                        <flux:input wire:model="p_sku" placeholder="{{ __('Auto-generated if empty') }}" />
                        @error('p_sku') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                    <div>
                        <flux:label>{{ __('Quantité') }}</flux:label>
                        <flux:input wire:model="p_quantity" type="number" min="0" required />
                        @error('p_quantity') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
            </div>
            <flux:button type="button" variant="ghost" wire:click="$set('p_showModal', false)">{{ __('Annulé') }}</flux:button>
            <flux:button type="submit">{{ __('Enregistré') }}</flux:button>
        </form>
    </flux:modal>

    <!-- Hairdresser Modal -->
    <flux:modal wire:model="h_showModal">
        <form wire:submit.prevent="h_save">
            <h3>{{ $h_editing ? 'Modidifié' : 'Créer' }} Coiffeur</h3>
            <div class="space-y-4">
                <div>
                    <flux:label>Nom</flux:label>
                    <flux:input wire:model="h_name" required />
                    @error('h_name') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div>
                    <flux:label>Contact</flux:label>
                    <flux:input wire:model="h_phone" />
                    @error('h_phone') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div>
                    <flux:label>Spécialité</flux:label>
                    <flux:input wire:model="h_specialty" />
                    @error('h_specialty') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
            </div>
            <flux:button type="button" variant="ghost" wire:click="$set('h_showModal', false)">Cancel</flux:button>
            <flux:button type="submit">Save</flux:button>
        </form>
    </flux:modal>

    <!-- Promotion Modal -->
    <flux:modal wire:model="promo_showModal">
        <form wire:submit.prevent="promo_save">
            <h3>{{ $promo_editing ? 'Modifier' : 'Créer' }} Promotion</h3>
            <div class="space-y-4">
                <div>
                    <flux:label>Nom</flux:label>
                    <flux:input wire:model="promo_name" required />
                    @error('promo_name') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:label>Type</flux:label>
                        <flux:select wire:model="promo_type">
                            <option value="percentage">Pourcentage global</option>
                            <option value="days">Par jours (jours de la semaine)</option>
                        </flux:select>
                        @error('promo_type') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                    <div>
                        <flux:label>Pourcentage (%)</flux:label>
                        <flux:input wire:model="promo_percentage" type="number" step="0.01" min="0" max="100" required />
                        @error('promo_percentage') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
                <div x-data="{ type: @entangle('promo_type') }">
                    <template x-if="type === 'days')"></template>
                </div>
                <div>
                    @if($promo_type === 'days')
                        <flux:label>Jours de la semaine</flux:label>
                        <div class="grid grid-cols-7 gap-2 text-sm">
                            @php $days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam']; @endphp
                            @foreach($days as $i => $d)
                                <label class="inline-flex items-center gap-1">
                                    <input type="checkbox" value="{{ $i }}" wire:model="promo_days"> {{ $d }}
                                </label>
                            @endforeach
                        </div>
                        @error('promo_days') <flux:error>{{ $message }}</flux:error> @enderror
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:label>Début</flux:label>
                        <flux:input type="date" wire:model="promo_starts_at" />
                        @error('promo_starts_at') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                    <div>
                        <flux:label>Fin</flux:label>
                        <flux:input type="date" wire:model="promo_ends_at" />
                        @error('promo_ends_at') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                </div>
            </div>
            <flux:button type="button" variant="ghost" wire:click="$set('promo_showModal', false)">Annuler</flux:button>
            <flux:button type="submit">Enregistrer</flux:button>
        </form>
    </flux:modal>

    <!-- Sale Modal -->
    <flux:modal wire:model="s_showModal">
        <form wire:submit.prevent="s_save">
            <h3>New Sale</h3>
            <div class="space-y-4">
                <div>
                    <flux:label>Boutique</flux:label>
                    <div class="mt-1 text-sm">{{ $shop->name }}</div>
                </div>
                <div>
                    <flux:label>Client</flux:label>
                    <flux:input wire:model="s_customer_name" type="text" placeholder="Enter customer name" required />
                    @error('s_customer_name') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <div>
                    <flux:label>Coiffeur (optionelle)</flux:label>
                    <flux:select wire:model="s_hairdresser_id">
                        <option value="">Selectionnez le coiffeur</option>
                        @foreach($s_availableHairdressers as $hd)
                            <option value="{{ $hd->id }}">{{ $hd->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label>Promotion (code)</flux:label>
                    <flux:select wire:model="s_promotion_id">
                        <option value="">Aucune</option>
                        @foreach($s_availablePromotions as $pr)
                            <option value="{{ $pr->id }}">{{ $pr->name }} ({{ number_format($pr->percentage, 0) }}%)</option>
                        @endforeach
                    </flux:select>
                    <small class="text-gray-500">Optionnel: Appliquer un code promotion à cette vente.</small>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <flux:label>Produits</flux:label>
                        <flux:button type="button" size="sm" wire:click="s_addProduct">Ajouter produits</flux:button>
                    </div>
                    @foreach($s_products as $index => $product)
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-5">
                                <flux:select wire:model="s_products.{{ $index }}.product_id" wire:change="s_updateProduct({{ $index }})" required>
                                    <option value="">Select Product</option>
                                    @foreach($s_availableProducts as $availableProduct)
                                        <option value="{{ $availableProduct->id }}">{{ $availableProduct->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="col-span-2">
                                <flux:input wire:model="s_products.{{ $index }}.quantity" type="number" min="1" wire:change="s_updateProduct({{ $index }})" required />
                            </div>
                            <div class="col-span-3">
                                <flux:input wire:model="s_products.{{ $index }}.unit_price" type="number" step="0.01" readonly />
                            </div>
                            <div class="col-span-2">
                                <flux:button type="button" size="sm" wire:click="s_removeProduct({{ $index }})">Supprimer</flux:button>
                            </div>
                        </div>
                    @endforeach
                    <div class="text-right mt-2">
                        <strong>Total: {{ number_format($s_total_amount, 2) }}</strong>
                    </div>
                </div>
            </div>
            <flux:button type="button" variant="ghost" wire:click="$set('s_showModal', false)">Annuler</flux:button>
            <flux:button type="submit">Enregistrer la vente</flux:button>
        </form>
    </flux:modal>

    <!-- Assign Hairdressers Modal -->
    <flux:modal wire:model="s_showAssignModal">
        <form wire:submit.prevent="s_saveAssignments">
            <h3>Assigner un coiffeur</h3>

            <div class="space-y-4">
                <div>
                    <flux:label>Le coiffeur</flux:label>
                    <flux:select wire:model="s_hairdresser_id">
                        <option value="">Selectionnez coiffeur</option>
                        @foreach($s_availableHairdressers as $hd)
                            <option value="{{ $hd->id }}">{{ $hd->name }}</option>
                        @endforeach
                    </flux:select>
                    <small class="text-gray-500">Optional: Assigner un coiffeur à la vente.</small>
                </div>

            </div>
            <flux:button type="button" variant="ghost" wire:click="$set('s_showAssignModal', false)">Modifier</flux:button>
            <flux:button type="submit">Enregister l'assignement</flux:button>
        </form>
    </flux:modal>

    <!-- Product Delete Confirmation Modal -->
    <flux:modal wire:model="p_showDeleteModal">
        <div class="space-y-4">
            <h3>Confirmez la selection</h3>
            <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="filled" wire:click="$set('p_showDeleteModal', false)">Annuler</flux:button>
                <flux:button type="button" variant="danger" wire:click="p_delete({{ $p_deleteId }})">Supprimer</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
