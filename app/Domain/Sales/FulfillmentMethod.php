<?php

namespace App\Domain\Sales;

/**
 * How the customer receives the goods (spec 5.5).
 */
enum FulfillmentMethod: string
{
    case Shipping = 'shipping';
    case Pickup = 'pickup';
    case InPerson = 'in_person';
}
