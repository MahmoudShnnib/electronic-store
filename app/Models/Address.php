<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'name', 'phone', 'country', 'city', 'area',
        'street', 'building', 'notes', 'is_default_shipping', 'is_default_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Snapshot stored on orders — decoupled from later edits to the address. */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'area' => $this->area,
            'street' => $this->street,
            'building' => $this->building,
            'notes' => $this->notes,
        ];
    }
}
