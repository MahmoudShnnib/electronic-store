<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method->value,
            'currency' => $this->currency,
            'discount' => Money::toDecimal($this->discount_cents),
            'total' => Money::toDecimal($this->total_cents),
            'coupon_code' => $this->coupon_code,
            'cancellable' => $this->resource->canBeCancelled(),
            'created_at' => $this->created_at->toIso8601String(),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->variant_name !== null
                    ? "{$item->product_name} — {$item->variant_name}"
                    : $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => Money::toDecimal($item->unit_price_cents),
                'line_total' => Money::toDecimal($item->line_total_cents),
            ])),
            'shipping_address' => $this->when($this->relationLoaded('items'), $this->shipping_address),
            'timeline' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'status' => $h->to_status,
                'comment' => $h->comment,
                'at' => $h->created_at->toIso8601String(),
            ])),
        ];
    }
}
