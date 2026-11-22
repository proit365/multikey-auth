<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use mysqli;
use mysqli_stmt;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;

final class MysqliKeyRepository implements KeyRepositoryInterface
{
    public function __construct(private readonly mysqli $mysqli)
    {
    }

    public function storeKey(string $poolId, KeyRecord $record): void
    {
        $statement = $this->mysqli->prepare(
            'INSERT INTO mkap_authentication_keys '
            . '(pool_id, public_identifier, encrypted_secret, encryption_key_version, status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );
        $status = $record->status->value;
        $identifier = $record->identifier;
        $encrypted = $record->encryptedSecret->toBinary();
        $keyVersion = $record->encryptedSecret->keyVersion;
        $createdAt = gmdate('Y-m-d H:i:s');
        $statement->bind_param(
            'sssiss',
            $poolId,
            $identifier,
            $encrypted,
            $keyVersion,
            $status,
            $createdAt
        );
        $statement->execute();
    }

    public function findActiveByIdentifiers(string $poolId, array $identifiers): array
    {
        if ($identifiers === []) {
            return [];
        }

        $identifiers = array_values($identifiers);
        $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
        $types = str_repeat('s', count($identifiers));
        $statement = $this->mysqli->prepare(
            'SELECT public_identifier, encrypted_secret, encryption_key_version, status, revoked_at '
            . 'FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ? AND public_identifier IN (' . $placeholders . ')'
        );

        $parameters = array_merge([$poolId, KeyStatus::Active->value], $identifiers);
        $bindTypes = 'ss' . $types;
        self::bindParameters($statement, $bindTypes, $parameters);
        $statement->execute();

        $matches = [];
        $identifier = null;
        $encryptedSecret = null;
        $keyVersion = null;
        $recordStatus = null;
        $revokedAt = null;
        $statement->bind_result($identifier, $encryptedSecret, $keyVersion, $recordStatus, $revokedAt);
        while ($statement->fetch()) {
            $encrypted = EncryptedSecret::fromBinary((string) $encryptedSecret);
            $matches[(string) $identifier] = new KeyRecord(
                (string) $identifier,
                $encrypted,
                KeyStatus::from((string) $recordStatus),
                $revokedAt !== null ? (strtotime((string) $revokedAt) ?: null) : null
            );
        }
        return $matches;
    }

    public function findActiveIdentifierAt(string $poolId, int $offset): ?string
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Key offset must not be negative.');
        }

        $statement = $this->mysqli->prepare(
            'SELECT public_identifier FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ? '
            . 'ORDER BY public_identifier LIMIT 1 OFFSET ?'
        );
        $status = KeyStatus::Active->value;
        $statement->bind_param('ssi', $poolId, $status, $offset);
        $statement->execute();
        $identifier = null;
        $statement->bind_result($identifier);
        return $statement->fetch() ? (string) $identifier : null;
    }

    public function revokeKey(string $poolId, string $identifier, int $timestamp): void
    {
        $statement = $this->mysqli->prepare(
            'UPDATE mkap_authentication_keys SET status = ?, revoked_at = ? '
            . 'WHERE pool_id = ? AND public_identifier = ?'
        );
        $status = KeyStatus::Revoked->value;
        $revokedAt = gmdate('Y-m-d H:i:s', $timestamp);
        $statement->bind_param(
            'ssss',
            $status,
            $revokedAt,
            $poolId,
            $identifier
        );
        $statement->execute();
        if ($statement->affected_rows !== 1) {
            throw new \RuntimeException('Unknown key identifier or key already revoked.');
        }
    }

    public function countActive(string $poolId): int
    {
        $statement = $this->mysqli->prepare(
            'SELECT COUNT(*) FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ?'
        );
        $status = KeyStatus::Active->value;
        $statement->bind_param('ss', $poolId, $status);
        $statement->execute();
        $count = 0;
        $statement->bind_result($count);
        $statement->fetch();
        return (int) $count;
    }

    private static function bindParameters(mysqli_stmt $statement, string $types, array &$parameters): void
    {
        $arguments = [$types];
        foreach ($parameters as $index => &$parameter) {
            $arguments[] = &$parameter;
        }
        unset($parameter);
        call_user_func_array([$statement, 'bind_param'], $arguments);
    }
}
