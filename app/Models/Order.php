<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_email', 'total', 'status', 'poison', 'processed_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'status' => OrderStatus::class,
            'poison' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OutboxMessage, $this>
     */
    public function outboxMessages(): HasMany
    {
        return $this->hasMany(OutboxMessage::class, 'aggregate_id');
    }
}
