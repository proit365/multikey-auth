<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Server;

use PDO;

final class PdoChallengeRepository implements ChallengeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function save(ChallengeRecord $challenge): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO mkap_authentication_challenges '
            . '(request_identifier, client_id, pool_id, pool_version, nonce, selected_key_identifiers, '
            . 'http_method, request_target, body_sha256, expires_at, status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $values = [
            $challenge->requestIdentifier,
            $challenge->clientRecordId,
            $challenge->poolRecordId,
            $challenge->poolVersion,
            $challenge->nonce,
            implode('', $challenge->keyIdentifiers),
            $challenge->method,
            $challenge->target,
            $challenge->bodySha256,
            gmdate('Y-m-d H:i:s', $challenge->expiresAt),
            $challenge->status,
            gmdate('Y-m-d H:i:s', $challenge->createdAt),
        ];
        foreach ($values as $index => $value) {
            $type = in_array($index, [0, 4, 8], true) ? PDO::PARAM_LOB : PDO::PARAM_STR;
            $statement->bindValue($index + 1, $value, $type);
        }
        $statement->execute();
    }

    public function findByRequestIdentifier(string $requestIdentifier): ?ChallengeRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT c.request_identifier, c.client_id, cl.client_identifier, c.pool_id, '
            . 'kp.pool_identifier, c.pool_version, c.nonce, c.selected_key_identifiers, '
            . 'kp.identifier_length, c.http_method, c.request_target, c.body_sha256, '
            . 'c.created_at, c.expires_at, c.status, c.consumed_at '
            . 'FROM mkap_authentication_challenges c '
            . 'JOIN mkap_clients cl ON cl.id = c.client_id '
            . 'JOIN mkap_key_pools kp ON kp.id = c.pool_id '
            . 'WHERE c.request_identifier = ?'
        );
        $statement->bindValue(1, $requestIdentifier, PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $idLength = (int) $row['identifier_length'];
        $ids = self::splitIdentifiers((string) $row['selected_key_identifiers'], $idLength);
        $createdAt = self::utcTimestamp((string) $row['created_at']);
        $expiresAt = self::utcTimestamp((string) $row['expires_at']);
        if ($createdAt === null || $expiresAt === null) {
            throw new \RuntimeException('Stored challenge timestamps are invalid.');
        }

        return new ChallengeRecord(
            (string) $row['request_identifier'],
            1,
            (int) $row['client_id'],
            (string) $row['client_identifier'],
            (int) $row['pool_id'],
            (string) $row['pool_identifier'],
            (int) $row['pool_version'],
            (string) $row['nonce'],
            $ids,
            $idLength,
            (string) $row['http_method'],
            (string) $row['request_target'],
            (string) $row['body_sha256'],
            $createdAt,
            $expiresAt,
            (string) $row['status'],
            isset($row['consumed_at']) ? self::utcTimestamp((string) $row['consumed_at']) : null
        );
    }

    public function consumeIfPending(string $requestIdentifier, int $now): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE mkap_authentication_challenges "
            . "SET status = 'consumed', consumed_at = ? "
            . "WHERE request_identifier = ? AND status = 'pending' AND expires_at > ?"
        );
        $timestamp = gmdate('Y-m-d H:i:s', $now);
        $statement->bindValue(1, $timestamp, PDO::PARAM_STR);
        $statement->bindValue(2, $requestIdentifier, PDO::PARAM_LOB);
        $statement->bindValue(3, $timestamp, PDO::PARAM_STR);
        $statement->execute();
        return $statement->rowCount() === 1;
    }

    private static function splitIdentifiers(string $joined, int $length): array
    {
        if ($length < 1 || strlen($joined) === 0 || strlen($joined) % $length !== 0) {
            throw new \RuntimeException('Stored challenge identifiers are malformed.');
        }
        return str_split($joined, $length);
    }

    private static function utcTimestamp(string $value): ?int
    {
        $formats = ['Y-m-d H:i:s.u', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat(
                '!' . $format,
                $value,
                new \DateTimeZone('UTC')
            );
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->getTimestamp();
            }
        }
        return null;
    }
}
