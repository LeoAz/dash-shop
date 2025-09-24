<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'type',
        'percentage',
        'days_of_week',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'days_of_week' => 'array',
        'active' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function isActiveForDate($date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if (!$this->active) {
            return false;
        }

        if ($this->starts_at && $date->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $date->gt($this->ends_at)) {
            return false;
        }

        if ($this->type === 'days') {
            $dow = (int) $date->dayOfWeek; // 0 (Sun) - 6 (Sat)
            $days = collect($this->days_of_week ?? [])->map(fn($d) => (int) $d)->all();
            return in_array($dow, $days, true);
        }

        // type === 'percentage' means always applies when active and in range
        return true;
    }
}
