<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ProIT365\MultiKeyAuth\LaravelServer\Http\ChallengeController;

$path = trim((string) config('multikey-auth-server.routes.challenge_path', 'v1/auth/challenges'), '/');
$middleware = (array) config('multikey-auth-server.routes.middleware', ['api']);

Route::middleware($middleware)->post($path, ChallengeController::class);
