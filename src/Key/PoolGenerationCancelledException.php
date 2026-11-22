<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Core\Key;

final class PoolGenerationCancelledException extends \RuntimeException
{
    public function __construct(public readonly GenerationBatch $batch)
    {
        parent::__construct('Key pool generation was cancelled.');
    }
}
