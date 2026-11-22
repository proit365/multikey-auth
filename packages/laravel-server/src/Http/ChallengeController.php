<?php

declare(strict_types=1);

namespace ProIT365\MultiKeyAuth\LaravelServer\Http;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ProIT365\MultiKeyAuth\Core\Encoding\Base64Url;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeIssuer;
use ProIT365\MultiKeyAuth\Core\Server\ChallengeRequest as CoreChallengeRequest;
use ProIT365\MultiKeyAuth\Core\Server\RequestTooLargeException;
use Throwable;

final class ChallengeController
{
    public function __construct(
        private readonly ChallengeIssuer $issuer,
        private readonly ResponseFactory $responses,
        private readonly ErrorResponder $errors,
        private readonly int $maximumBodyBytes
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        if (strlen($body) > $this->maximumBodyBytes) {
            return $this->errors->respond(new RequestTooLargeException());
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)
                || array_is_list($data)
                || ($data['version'] ?? null) !== 1
                || !is_string($data['client_id'] ?? null)
                || !is_string($data['method'] ?? null)
                || !is_string($data['target'] ?? null)
                || !is_string($data['body_sha256'] ?? null)) {
                throw new \InvalidArgumentException('Malformed challenge request.');
            }

            $bodyHash = Base64Url::decode($data['body_sha256'], 32);
            if (Base64Url::encode($bodyHash) !== $data['body_sha256']) {
                throw new \InvalidArgumentException('Non-canonical body hash.');
            }
            $challenge = $this->issuer->issue(new CoreChallengeRequest(
                $data['version'],
                $data['client_id'],
                $data['method'],
                $data['target'],
                $bodyHash
            ));

            return $this->responses->json($challenge->toArray())
                ->header('Cache-Control', 'no-store');
        } catch (Throwable $error) {
            return $this->errors->respond($error);
        }
    }
}
