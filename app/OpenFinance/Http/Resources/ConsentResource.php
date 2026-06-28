<?php

namespace App\OpenFinance\Http\Resources;

use App\Projections\Models\Consent;

final class ConsentResource
{
    /** @return array<string, mixed> */
    public static function fromModel(Consent $consent): array
    {
        return [
            'consentId' => $consent->consent_id,
            'creationDateTime' => $consent->creation_date_time?->utc()->format('Y-m-d\TH:i:s\Z'),
            'status' => $consent->status,
            'statusUpdateDateTime' => $consent->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'permissions' => $consent->permissions,
            'expirationDateTime' => $consent->expiration_date_time?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
