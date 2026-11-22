<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [];

function runCheck(string $name, array $command): void
{
    global $failures, $checks;
    $process = proc_open($command, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $GLOBALS['root']);
    if (!is_resource($process)) {
        $failures[] = $name . ': unable to start';
        return;
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $checks[$name] = $code === 0;
    if ($code !== 0) {
        $failures[] = $name . ': ' . trim($stdout . "\n" . $stderr);
    }
}

runCheck('core-server', ['php', 'packages/core/tests/manual-phase06.php']);
runCheck('php-client', ['php', 'packages/client-php/tests/manual-phase07.php']);
runCheck('laravel-server', ['php', 'packages/laravel-server/tests/manual-phase08.php']);
runCheck('laravel-client', ['php', 'packages/laravel-client/tests/manual-phase09.php']);
runCheck('javascript', ['node', 'packages/javascript/test/interoperability.test.js']);
runCheck('cli', ['php', 'packages/cli/tests/manual-phase12.php']);

$phpFiles = [];
$directories = [
    'packages/core/src',
    'packages/server/src',
    'packages/client-php/src',
    'packages/laravel-server/src',
    'packages/laravel-client/src',
    'packages/cli/src',
];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $phpFiles[] = $file->getPathname();
        }
    }
}
foreach ($phpFiles as $file) {
    runCheck('php-lint:' . basename($file), ['php', '-l', $file]);
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . PHP_EOL);
    }
    exit(1);
}

foreach ($checks as $name => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL);
}
