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
        $promotion = $promotion ?: ($this->shop ? $this->shop->activePromotionForDate($this->sale_date) : null);
        if ($promotion) {
            $this->promotion_id = $promotion->id;
            $discount = round(((float)$promotion->percentage / 100) * (float)$this->total_amount, 2);
            $this->discount_amount = $discount;
        } else {
            $this->promotion_id = null;
            $this->discount_amount = null;
        }
    }
}
