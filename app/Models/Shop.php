<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Shop extends Model
{
    /** @use HasFactory<\Database\Factories\ShopFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function hairdressers()
    {
        return $this->hasMany(Hairdresser::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function activePromotionForDate($date): ?Promotion
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $this->promotions
            ->filter(fn (Promotion $p) => $p->isActiveForDate($date))
            ->sortByDesc('percentage')
            ->first();
    }
}
