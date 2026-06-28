<?php

namespace Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final class SchemaValidator
{
    public static function validate(object|array $data, string $schemaPath): void
    {
        $validator = new Validator;
        $schema = json_decode((string) file_get_contents($schemaPath));
        $payload = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $result = $validator->validate($payload, $schema);

        if ($result->isValid()) {
            return;
        }

        $formatter = new ErrorFormatter;
        $errors = $formatter->format($result->error());

        throw new \RuntimeException(
            'Schema validation failed: '.json_encode($errors, JSON_THROW_ON_ERROR),
        );
    }
}
