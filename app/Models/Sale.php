<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory;
    protected $fillable = [
        'shop_id',
        'hairdresser_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'sale_date',
        'total_amount',
        'status',
        'promotion_id',
        'discount_amount',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hairdresser()
    {
        return $this->belongsTo(Hairdresser::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_sales')
            ->withPivot('quantity', 'unit_price', 'subtotal')
            ->withTimestamps();
    }

    public function productSales()
    {
        return $this->hasMany(ProductSale::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function applyPromotion(?Promotion $promotion = null): void
    {
        // Determine the candidate promotion in order of precedence:
        // 1) Explicitly provided promotion
        // 2) Promotion already selected on the sale (promotion_id)
        // 3) Shop-level active promotion for the sale date
        $date = $this->sale_date ?: now();

        if (!$promotion && $this->promotion_id) {
            $promotion = Promotion::find($this->promotion_id);
        }
        if (!$promotion && $this->shop) {
            $promotion = $this->shop->activePromotionForDate($date);
        }

        if ($promotion && $promotion->isActiveForDate($date)) {
            // Determine discount: prefer percentage when > 0, otherwise use fixed amount when > 0
            $base = (float) $this->total_amount;
            $pct = (float) ($promotion->percentage ?? 0);
            $amt = (float) ($promotion->amount ?? 0);
            $discount = 0.0;

            if ($pct > 0) {
                $discount = round(($pct / 100) * $base, 2);
            } elseif ($amt > 0) {
                // Cap fixed amount discount to the base total to avoid negative totals
                $discount = round(min($amt, $base), 2);
            }

            if ($discount > 0) {
                $this->promotion_id = $promotion->id;
                $this->discount_amount = $discount;
                return;
            }
        }

        // No applicable promotion
        $this->promotion_id = null;
        $this->discount_amount = null;
    }
}
