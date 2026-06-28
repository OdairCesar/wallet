<?php

namespace App\Payments\Enums;

final class PaymentEventType
{
    public const PixInitiated = 'payments.pix.initiated';

    public const PixCompleted = 'payments.pix.completed';

    public const PixRejected = 'payments.pix.rejected';

    public const PixCancelled = 'payments.pix.cancelled';
}
