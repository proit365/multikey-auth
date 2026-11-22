<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Cli;

final class AuditLogger
{
    public function __construct(private readonly ?string $auditPath)
    {
    }

    public function record(
        string $action,
        string $result,
        array $metadata = []
    ): void {
        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'action' => $action,
            'result' => $result,
            'metadata' => $metadata,
        ];
        if ($this->auditPath !== null) {
            $directory = dirname($this->auditPath);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create audit-log directory.');
            }
            $line = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
            if (file_put_contents($this->auditPath, $line, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Unable to append the audit log.');
            }
        }
        fwrite(STDOUT, 'AUDIT ' . $record['timestamp'] . ' '
            . $action . ' ' . $result . PHP_EOL);
    }
}
