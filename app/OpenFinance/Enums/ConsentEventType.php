<?php

namespace App\OpenFinance\Enums;

final class ConsentEventType
{
    public const Requested = 'consent.requested';

    public const Authorised = 'consent.authorised';

    public const Rejected = 'consent.rejected';

    public const Revoked = 'consent.revoked';
}
