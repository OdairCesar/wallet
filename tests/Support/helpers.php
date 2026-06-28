<?php

function loadContractFixture(string $relativePath): array
{
    $path = base_path('tests/Contracts/Fixtures/'.$relativePath);

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function assertResponseStructure(array $actual, array $expected, array $ignoreKeys = []): void
{
    foreach ($expected as $key => $value) {
        if (in_array($key, $ignoreKeys, true)) {
            continue;
        }

        expect($actual)->toHaveKey($key);

        if (is_array($value) && is_array($actual[$key])) {
            assertResponseStructure($actual[$key], $value, $ignoreKeys);
        } elseif (! is_array($value)) {
            expect($actual[$key])->toBe($value);
        }
    }
}
