<?php

use App\Models\Hairdresser;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

layout('components.layouts.app');
title('Dashboard');

state(['selectedShopId' => null, 'startDate' => null, 'endDate' => null]);

$shops = computed(fn () => Shop::orderBy('name')->get(['id','name']));

$dailySales = computed(function () {
    $query = Sale::query();
    if ($this->selectedShopId) {
        $query->where('shop_id', $this->selectedShopId);
    }

    // Determine date range (default last 30 days)
    $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::today()->subDays(29)->startOfDay();
    $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::today()->endOfDay();
    if ($end->lt($start)) {
        [$start, $end] = [$end->clone()->startOfDay(), $start->clone()->endOfDay()];
    }

    $salesByDay = $query
        ->whereDate('sale_date', '>=', $start)
        ->whereDate('sale_date', '<=', $end)
        ->selectRaw('DATE(sale_date) as day, SUM(total_amount) as total')
        ->groupBy('day')
        ->orderBy('day')
        ->pluck('total', 'day');

    // Ensure continuous range with zeros
    $days = collect();
    for ($d = $start->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
        $days->push($d->toDateString());
    }

    return $days->mapWithKeys(fn ($d) => [$d => (float) ($salesByDay[$d] ?? 0.0)]);
});

$topHairdressers = computed(function () {
    $query = Sale::query();
    if ($this->selectedShopId) {
        $query->where('shop_id', $this->selectedShopId);
    }
    return $query
        ->whereNotNull('hairdresser_id')
        ->selectRaw('hairdresser_id, SUM(total_amount) as revenue, COUNT(*) as orders')
        ->groupBy('hairdresser_id')
        ->orderByDesc('revenue')
        ->with('hairdresser:id,name')
        ->limit(5)
        ->get();
});

$hairdresserPerformance = computed(function () {
    $query = Sale::query();
    if ($this->selectedShopId) {
        $query->where('shop_id', $this->selectedShopId);
    }
    return $query
        ->whereNotNull('hairdresser_id')
        ->selectRaw('hairdresser_id, SUM(total_amount) as revenue, COUNT(*) as orders, AVG(total_amount) as avg_ticket')
        ->groupBy('hairdresser_id')
        ->with('hairdresser:id,name')
        ->orderByDesc('revenue')
        ->get();
});

$inventoryStatus = computed(function () {
    $products = Product::query()
        ->when($this->selectedShopId, fn ($q) => $q->where('shop_id', $this->selectedShopId))
        ->get(['id','name','quantity']);

    $outOfStock = $products->where('quantity', '<=', 0)->values();
    $lowStock = $products->filter(fn ($p) => $p->quantity > 0 && $p->quantity <= 5)->values();

    return [
        'total' => $products->count(),
        'out' => $outOfStock->count(),
        'low' => $lowStock->count(),
        'ok' => $products->count() - ($outOfStock->count() + $lowStock->count()),
        'out_list' => $outOfStock,
        'low_list' => $lowStock,
    ];
});

$salesChart = computed(function () {
    $values = array_values($this->dailySales->all());
    $width = 600; $height = 160; $pad = 10; $gap = 2;
    $max = max(1, (float) max($values ?: [0]));
    $count = max(1, count($values));

    $inner = $width - 2*$pad;
    $barWidth = $count > 0 ? max(1, floor(($inner - $gap * max(0, $count - 1)) / $count)) : $inner;

    $bars = [];
    foreach ($values as $i => $v) {
        $x = $pad + $i * ($barWidth + $gap);
        $barH = ($v / $max) * ($height - 2*$pad);
        $y = $height - $pad - $barH;
        $bars[] = [
            'x' => $x,
            'y' => $y,
            'width' => $barWidth,
            'height' => $barH,
        ];
    }
    return [
        'bars' => $bars,
        'width' => $width,
        'height' => $height,
        'pad' => $pad,
        'total' => array_sum($values),
    ];
});

?>

<div class="space-y-6">
    <div class="flex items-center gap-4 flex-wrap">
        <div class="min-w-64">
            <flux:select wire:model.live="selectedShopId" placeholder="Toutes les boutiques">
                <flux:select.option value="">Toutes les boutiques</flux:select.option>
                @foreach($this->shops as $shop)
                    <flux:select.option value="{{ $shop->id }}">{{ $shop->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="flex items-center gap-2">
            <flux:input type="date" wire:model.live="startDate" placeholder="Date de début" />
            <span class="text-neutral-500">à</span>
            <flux:input type="date" wire:model.live="endDate" placeholder="Date de fin" />
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="col-span-2 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Ventes quotidiennes</h3>
                <div class="text-sm text-neutral-500">Total : {{ number_format($this->salesChart['total'], 2) }}</div>
            </div>
            <div class="mt-3" style="height: 200px;">
                <canvas id="dailySalesChart"></canvas>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (function () {
                    const el = document.getElementById('dailySalesChart');
                    if (!el) return;
                    const ctx = el.getContext('2d');

                    const labels = @json(collect($this->dailySales->keys())->map(fn($d) => \Illuminate\Support\Carbon::parse($d)->format('m/d'))->values());
                    const data = @json(array_values($this->dailySales->all()));

                    if (window._dailySalesChart) {
                        try { window._dailySalesChart.destroy(); } catch (e) {}
                    }

                    window._dailySalesChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Ventes',
                                data,
                                backgroundColor: 'rgba(37, 99, 235, 0.7)',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 1,
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: { display: false, drawBorder: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { display: false, drawBorder: false }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const v = ctx.parsed.y;
                                            return typeof v === 'number' ? v.toFixed(2) : v;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })();
            </script>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <h3 class="font-semibold mb-3">Meilleurs coiffeurs</h3>
            <div class="space-y-3">
                @forelse($this->topHairdressers as $row)
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $row->hairdresser->name ?? 'N/A' }}</div>
                            <div class="text-xs text-neutral-500">Commandes : {{ $row->orders }}</div>
                        </div>
                        <div class="font-semibold">{{ number_format($row->revenue, 2) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-neutral-500">Aucune donnée</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <h3 class="font-semibold mb-3">Performance des coiffeurs</h3>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nom</flux:table.column>
                    <flux:table.column>Chiffre d'affaires</flux:table.column>
                    <flux:table.column>Commandes</flux:table.column>
                    <flux:table.column>Panier moyen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->hairdresserPerformance as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row->hairdresser->name ?? 'N/A' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row->revenue, 2) }}</flux:table.cell>
                            <flux:table.cell>{{ $row->orders }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row->avg_ticket, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                <div class="text-sm text-neutral-500">No data</div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <h3 class="font-semibold mb-3">État des stocks</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg border p-3">
                    <div class="text-xs text-neutral-500">OK</div>
                    <div class="text-2xl font-semibold">{{ $this->inventoryStatus['ok'] }}</div>
                </div>
                <div class="rounded-lg border p-3">
                    <div class="text-xs text-neutral-500">Faible</div>
                    <div class="text-2xl font-semibold text-amber-600">{{ $this->inventoryStatus['low'] }}</div>
                </div>
                <div class="rounded-lg border p-3">
                    <div class="text-xs text-neutral-500">Rupture</div>
                    <div class="text-2xl font-semibold text-red-600">{{ $this->inventoryStatus['out'] }}</div>
                </div>
            </div>
            <div class="mt-4" style="height: 200px;">
                <canvas id="inventoryChart"></canvas>
            </div>
            <script>
                (function () {
                    const el = document.getElementById('inventoryChart');
                    if (!el) return;
                    const ctx = el.getContext('2d');
                    const labels = ['OK', 'Faible', 'Rupture'];
                    const data = [{{ $this->inventoryStatus['ok'] }}, {{ $this->inventoryStatus['low'] }}, {{ $this->inventoryStatus['out'] }}];
                    if (window._inventoryChart) {
                        try { window._inventoryChart.destroy(); } catch (e) {}
                    }
                    window._inventoryChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels,
                            datasets: [{
                                data,
                                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            },
                            cutout: '60%'
                        }
                    });
                })();
            </script>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <div class="text-sm font-medium mb-2">Stock faible (<=5)</div>
                    <ul class="space-y-1 max-h-40 overflow-auto">
                        @forelse($this->inventoryStatus['low_list'] as $prod)
                            <li class="flex justify-between text-sm"><span>{{ $prod->name }}</span> <span class="text-amber-600">{{ $prod->quantity }}</span></li>
                        @empty
                            <li class="text-sm text-neutral-500">Aucun</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <div class="text-sm font-medium mb-2">Rupture de stock</div>
                    <ul class="space-y-1 max-h-40 overflow-auto">
                        @forelse($this->inventoryStatus['out_list'] as $prod)
                            <li class="flex justify-between text-sm"><span>{{ $prod->name }}</span> <span class="text-red-600">0</span></li>
                        @empty
                            <li class="text-sm text-neutral-500">Aucun</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
