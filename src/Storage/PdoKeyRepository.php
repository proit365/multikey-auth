<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Storage;

use PDO;
use ProIT365\MultiKeyAuth\Core\Key\KeyStatus;

final class PdoKeyRepository implements KeyRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function storeKey(string $poolId, KeyRecord $record): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO mkap_authentication_keys '
            . '(pool_id, public_identifier, encrypted_secret, encryption_key_version, status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $poolId,
            $record->identifier,
            $record->encryptedSecret->toBinary(),
            $record->encryptedSecret->keyVersion,
            $record->status->value,
            gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function findActiveByIdentifiers(string $poolId, array $identifiers): array
    {
        if ($identifiers === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
        $statement = $this->pdo->prepare(
            'SELECT public_identifier, encrypted_secret, encryption_key_version, status, revoked_at '
            . 'FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ? AND public_identifier IN (' . $placeholders . ')'
        );
        $statement->execute(
            array_merge([$poolId, KeyStatus::Active->value], array_values($identifiers))
        );

        $matches = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $encrypted = EncryptedSecret::fromBinary((string) $row['encrypted_secret']);
            $matches[$row['public_identifier']] = new KeyRecord(
                (string) $row['public_identifier'],
                $encrypted,
                KeyStatus::from((string) $row['status']),
                isset($row['revoked_at']) ? strtotime((string) $row['revoked_at']) ?: null : null
            );
        }
        return $matches;
    }

    public function findActiveIdentifierAt(string $poolId, int $offset): ?string
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Key offset must not be negative.');
        }

        $statement = $this->pdo->prepare(
            'SELECT public_identifier FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ? '
            . 'ORDER BY public_identifier LIMIT 1 OFFSET ?'
        );
        $statement->bindValue(1, $poolId);
        $statement->bindValue(2, KeyStatus::Active->value);
        $statement->bindValue(3, $offset, PDO::PARAM_INT);
        $statement->execute();
        $identifier = $statement->fetchColumn();
        return $identifier === false ? null : (string) $identifier;
    }

    public function revokeKey(string $poolId, string $identifier, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE mkap_authentication_keys SET status = ?, revoked_at = ? '
            . 'WHERE pool_id = ? AND public_identifier = ?'
        );
        $statement->execute([
            KeyStatus::Revoked->value,
            gmdate('Y-m-d H:i:s', $timestamp),
            $poolId,
            $identifier,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Unknown key identifier or key already revoked.');
        }
    }

    public function countActive(string $poolId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mkap_authentication_keys '
            . 'WHERE pool_id = ? AND status = ?'
        );
        $statement->execute([$poolId, KeyStatus::Active->value]);
        return (int) $statement->fetchColumn();
    }
}
