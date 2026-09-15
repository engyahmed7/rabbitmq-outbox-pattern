<?php

namespace App\Enums;

enum DomainEvent: string
{
    case OrderCreated = 'order.created';
    case OrderCancelled = 'order.cancelled';
}
