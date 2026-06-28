<?php

namespace App\Payments\Enums;

enum PixPaymentStatus: string
{
    case Received = 'RCVD';
    case Accepted = 'ACCP';
    case Processing = 'ACPD';
    case Completed = 'ACSC';
    case Rejected = 'RJCT';
    case Cancelled = 'CANC';
}
