<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A call to confirm a cash-on-delivery order: who called, and what came of it. */
final class OrderCallAttempt extends Model
{
    public const UPDATED_AT = null;

    public const CONFIRMED = 'confirmed';

    public const NO_ANSWER = 'no_answer';

    protected $fillable = ['order_id', 'user_id', 'outcome', 'note'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
