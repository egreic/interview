<?php

declare(strict_types=1);

namespace App\Module\Platform\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'Health check',
        description: 'Liveness probe — returns ok when the application is up.',
        tags: ['platform'],
    )]
    #[OA\Response(
        response: 200,
        description: 'The application is up.',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')],
            type: 'object',
        ),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
