<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Export;

use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;

final class ExportValidator
{
    public function validate(array $manifest): array
    {
        $required = [
            'format_version',
            'protocol_version',
            'client_id',
            'pool_id',
            'pool_version',
            'export_version',
            'created_at',
            'identifier_length',
            'identifier_alphabet',
            'secret_length_bytes',
            'key_count',
            'keys',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $manifest)) {
                throw new \InvalidArgumentException('Export manifest is missing field: ' . $field);
            }
        }

        if ($manifest['format_version'] !== 1 || $manifest['protocol_version'] !== 1) {
            throw new \InvalidArgumentException('Unsupported export or protocol version.');
        }
        if (!is_int($manifest['identifier_length']) || $manifest['identifier_length'] < 1 || $manifest['identifier_length'] > 256) {
            throw new \InvalidArgumentException('Invalid identifier length.');
        }
        if (!is_string($manifest['identifier_alphabet']) || $manifest['identifier_alphabet'] === '') {
            throw new \InvalidArgumentException('Invalid identifier alphabet.');
        }
        if (strlen($manifest['identifier_alphabet']) !== count(array_unique(str_split($manifest['identifier_alphabet'])))) {
            throw new \InvalidArgumentException('Identifier alphabet contains duplicates.');
        }
        if ($manifest['secret_length_bytes'] !== 32) {
            throw new \InvalidArgumentException('Export secret length must be 32 bytes.');
        }
        if (!is_int($manifest['key_count']) || $manifest['key_count'] < 1) {
            throw new \InvalidArgumentException('Invalid export key count.');
        }
        if (!is_array($manifest['keys']) || count($manifest['keys']) !== $manifest['key_count']) {
            throw new \InvalidArgumentException('Export key count does not match the keys array.');
        }

        $validated = [];
        $seen = [];
        foreach ($manifest['keys'] as $key) {
            if (!is_array($key) || !isset($key['id'], $key['secret'])) {
                throw new \InvalidArgumentException('Malformed export key record.');
            }
            if (!is_string($key['id']) || strlen($key['id']) !== $manifest['identifier_length']) {
                throw new \InvalidArgumentException('Export key identifier has an inconsistent length.');
            }
            if (isset($seen[$key['id']])) {
                throw new \InvalidArgumentException('Duplicate export key identifier.');
            }
            for ($i = 0; $i < strlen($key['id']); $i++) {
                if (strpos($manifest['identifier_alphabet'], $key['id'][$i]) === false) {
                    throw new \InvalidArgumentException('Export key identifier contains an invalid character.');
                }
            }
            $secret = Base64Url::decode($key['secret'], 32);
            $seen[$key['id']] = true;
            $validated[] = ['id' => $key['id'], 'secret' => $secret];
        }

        return $validated;
    }
}
