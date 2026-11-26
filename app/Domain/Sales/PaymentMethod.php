<?php

namespace App\Domain\Sales;

/**
 * How the customer paid (spec 5.5). Manual entry — no gateway in the MVP.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case CardToCard = 'card_to_card';
    case Pos = 'pos';
    case Online = 'online';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';
}
