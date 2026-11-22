<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\Client;

interface HttpTransportInterface
{
    public function send(HttpRequest $request): HttpResponse;
}
