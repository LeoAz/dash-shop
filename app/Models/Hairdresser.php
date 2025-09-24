<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hairdresser extends Model
{
    /** @use HasFactory<\Database\Factories\HairdresserFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'name',
        'phone',
        'specialty',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
