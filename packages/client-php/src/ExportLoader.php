<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

use ProIT365\MultiKeyAuth\Core\Export\ExportValidator;

final class ExportLoader
{
    public function __construct(private readonly int $maximumExportBytes = 67_108_864)
    {
        if ($maximumExportBytes < 1 || $maximumExportBytes > 268_435_456) {
            throw new \InvalidArgumentException('Maximum export size is outside the supported range.');
        }
    }

    public function fromJson(string $json): ClientKeyPool
    {
        if (strlen($json) > $this->maximumExportBytes) {
            throw new \InvalidArgumentException('Export exceeds the configured size limit.');
        }
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new \InvalidArgumentException('Export manifest must be a JSON object.');
        }
        foreach (['client_id', 'pool_id', 'created_at', 'identifier_alphabet'] as $field) {
            if (!isset($manifest[$field]) || !is_string($manifest[$field]) || $manifest[$field] === '') {
                throw new \InvalidArgumentException('Export manifest has invalid metadata: ' . $field);
            }
        }
        foreach (['pool_version', 'export_version', 'identifier_length'] as $field) {
            if (!isset($manifest[$field]) || !is_int($manifest[$field]) || $manifest[$field] < 1) {
                throw new \InvalidArgumentException('Export manifest has invalid metadata: ' . $field);
            }
        }

        $validatedKeys = (new ExportValidator())->validate($manifest);
        $secrets = [];
        foreach ($validatedKeys as $key) {
            $secrets[$key['id']] = $key['secret'];
        }

        return new ClientKeyPool(
            $manifest['client_id'],
            $manifest['pool_id'],
            $manifest['pool_version'],
            $manifest['identifier_length'],
            $manifest['identifier_alphabet'],
            $secrets
        );
    }
}
