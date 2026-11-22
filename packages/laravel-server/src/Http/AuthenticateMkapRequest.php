<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Http;

use Closure;
use Illuminate\Http\Request;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticatedRequest;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationFailedException;
use ProIT365\MultiKeyAuth\Core\Server\AuthenticationVerifier;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AuthenticateMkapRequest
{
    public function __construct(
        private readonly AuthenticationVerifier $verifier,
        private readonly ErrorResponder $errors
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->headers->get('X-MKAP-Version');
        if ($version !== '1') {
            return $this->errors->respond(new \InvalidArgumentException('Unsupported protocol version.'));
        }

        $client = $request->headers->get('X-MKAP-Client');
        $requestId = $request->headers->get('X-MKAP-Request');
        $proof = $request->headers->get('X-MKAP-Proof');
        if (!is_string($client) || $client === ''
            || !is_string($requestId) || !is_string($proof)) {
            return $this->errors->respond(new AuthenticationFailedException());
        }

        try {
            $decodedRequestId = Base64Url::decode($requestId, 16);
            $decodedProof = Base64Url::decode($proof, 32);
        } catch (\InvalidArgumentException) {
            return $this->errors->respond(new AuthenticationFailedException());
        }
        if (Base64Url::encode($decodedRequestId) !== $requestId
            || Base64Url::encode($decodedProof) !== $proof) {
            return $this->errors->respond(new AuthenticationFailedException());
        }

        try {
            $this->verifier->verify(new AuthenticatedRequest(
                $client,
                $decodedRequestId,
                $decodedProof,
                strtoupper($request->getMethod()),
                $request->getRequestUri(),
                $request->getContent(),
                1
            ));
        } catch (Throwable $error) {
            return $this->errors->respond($error);
        }

        return $next($request);
    }
}
