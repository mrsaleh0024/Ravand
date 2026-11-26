<?php

namespace App\Domain\Sales;

/**
 * Where the sale happened (spec 5.5) — social sellers record the channel
 * manually with every order.
 */
enum SalesChannel: string
{
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';
    case Telegram = 'telegram';
    case Direct = 'direct';
    case InPerson = 'in_person';
    case Other = 'other';
}
