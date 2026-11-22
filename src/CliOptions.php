<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Cli;

final class CliOptions
{
    /** @param list<string> $arguments */
    public function __construct(private readonly array $arguments)
    {
    }

    public function command(): string
    {
        return $this->arguments[1] ?? 'help';
    }

    public function subcommand(): string
    {
        return $this->arguments[2] ?? '';
    }

    public function value(string $name): ?string
    {
        $prefix = '--' . $name . '=';
        foreach ($this->arguments as $argument) {
            if (str_starts_with($argument, $prefix)) {
                return substr($argument, strlen($prefix));
            }
        }
        return null;
    }

    public function positional(int $index): ?string
    {
        $values = [];
        foreach (array_slice($this->arguments, 3) as $argument) {
            if ($argument === '' || $argument[0] === '-') {
                continue;
            }
            $values[] = $argument;
        }
        $position = $index - 1;
        return $values[$position] ?? null;
    }

    public function has(string $name): bool
    {
        return in_array('--' . $name, $this->arguments, true)
            || $this->value($name) !== null;
    }
}
