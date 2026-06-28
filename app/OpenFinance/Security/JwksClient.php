<?php

namespace App\OpenFinance\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class JwksClient
{
    /**
     * @return array{kid: string, pem: string}
     */
    public function resolveKey(string $jwksUri, ?string $kid): array
    {
        $jwks = Cache::remember("of_jwks:{$jwksUri}", 3600, function () use ($jwksUri) {
            $response = Http::timeout(5)->get($jwksUri);

            if (! $response->successful()) {
                throw new InvalidArgumentException('Falha ao obter JWKS.');
            }

            return $response->json();
        });

        $keys = $jwks['keys'] ?? [];

        foreach ($keys as $key) {
            if ($kid !== null && ($key['kid'] ?? null) !== $kid) {
                continue;
            }

            if (($key['kty'] ?? '') !== 'RSA' || ! isset($key['n'], $key['e'])) {
                continue;
            }

            return [
                'kid' => (string) ($key['kid'] ?? ''),
                'pem' => $this->jwkToPem($key['n'], $key['e']),
            ];
        }

        throw new InvalidArgumentException('Chave pública não encontrada no JWKS.');
    }

    private function jwkToPem(string $n, string $e): string
    {
        $modulus = $this->base64UrlDecode($n);
        $exponent = $this->base64UrlDecode($e);

        $rsaPublicKey = pack(
            'Ca*a*a*a*',
            48,
            $this->encodeLength(strlen($modulus) + strlen($exponent) + 2),
            pack('Ca*a*', 2, $this->encodeLength(strlen($modulus)), $modulus),
            pack('Ca*a*', 2, $this->encodeLength(strlen($exponent)), $exponent),
        );

        $subjectPublicKeyInfo = pack(
            'Ca*a*a*',
            48,
            $this->encodeLength(strlen($rsaPublicKey) + 19),
            hex2bin('300d06092a864886f70d0101010500'),
            pack('Ca*a*', 3, $this->encodeLength(strlen($rsaPublicKey) + 1), "\x00".$rsaPublicKey),
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            .'-----END PUBLIC KEY-----';
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    private function encodeLength(int $length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), chr(0));

        return chr(0x80 | strlen($temp)).$temp;
    }
}
