<?php

namespace App\Models;

use Database\Factories\ProcessedMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['message_id', 'queue', 'event_type', 'payload'])]
class ProcessedMessage extends Model
{
    /** @use HasFactory<ProcessedMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
