<?php

use App\OpenFinance\Adapters\Dto\PixPaymentRequest;
use App\OpenFinance\Adapters\MockParticipantAdapter;

describe('Participant adapter normalized outputs', function () {
    it('returns consistent account list shape', function () {
        $adapter = new MockParticipantAdapter;
        $output = $adapter->getAccounts('urn:wallet:consent:test')->toArray();

        expect($output)->toHaveCount(1)
            ->and($output[0])->toHaveKeys(['accountId', 'type', 'brandName', 'currency']);
    });

    it('returns consistent pix payment response shape', function () {
        $adapter = new MockParticipantAdapter;

        $output = $adapter->initiatePixPayment(new PixPaymentRequest(
            consentId: 'urn:wallet:consent:test',
            amountCents: 15000,
            currency: 'BRL',
            localInstrument: 'DICT',
        ))->toArray();

        expect($output)->toHaveKeys(['paymentId', 'consentId', 'status', 'amountCents', 'currency'])
            ->and($output['status'])->toBe('RCVD')
            ->and($output['amountCents'])->toBe(15000);
    });
});
