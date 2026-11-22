<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Database;

use PDO;
use ProIT365\MultiKeyAuth\Core\Key\PoolMetadata;
use ProIT365\MultiKeyAuth\Core\Server\ClientPool;
use ProIT365\MultiKeyAuth\Core\Server\ClientPoolProviderInterface;

final class PdoClientPoolProvider implements ClientPoolProviderInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActivePool(string $clientIdentifier): ?ClientPool
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id AS client_record_id, c.client_identifier, p.id AS pool_record_id, '
            . 'p.pool_identifier, p.pool_version, p.identifier_length, p.identifier_alphabet, '
            . 'p.key_count, p.selection_count '
            . 'FROM mkap_clients c '
            . 'INNER JOIN mkap_key_pools p ON p.client_id = c.id '
            . 'WHERE c.client_identifier = ? AND c.status = ? AND p.status = ? '
            . 'ORDER BY p.pool_version DESC LIMIT 2'
        );
        $statement->execute([$clientIdentifier, 'active', 'active']);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return null;
        }
        if (count($rows) !== 1) {
            throw new \LogicException('A client must have exactly one active key pool.');
        }

        $row = $rows[0];
        $metadata = new PoolMetadata(
            (string) $row['pool_identifier'],
            (int) $row['pool_version'],
            (int) $row['identifier_length'],
            (string) $row['identifier_alphabet'],
            (int) $row['key_count'],
            (int) $row['selection_count']
        );

        return new ClientPool(
            (int) $row['client_record_id'],
            (int) $row['pool_record_id'],
            (string) $row['client_identifier'],
            $metadata
        );
    }
}
