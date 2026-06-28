<?php

namespace App\OpenFinance\Enums;

enum ConsentStatus: string
{
    case AwaitingAuthorisation = 'AWAITING_AUTHORISATION';
    case Authorised = 'AUTHORISED';
    case Rejected = 'REJECTED';
    case Revoked = 'REVOKED';
}
