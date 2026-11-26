<?php

namespace App\Domain\Sales;

/**
 * Product kinds — physical only in the MVP, extensible per spec 5.3.
 */
enum ProductType: string
{
    case Physical = 'physical';
}
