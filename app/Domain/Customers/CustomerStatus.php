<?php

namespace App\Domain\Customers;

/**
 * Customer lifecycle status (spec 5.2).
 *
 * Deleting a customer is, by default, archiving (spec 4.5): the row stays
 * for financial history; archived customers are excluded from the default
 * list view but remain reachable through the status filter.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
    case Archived = 'archived';
}
