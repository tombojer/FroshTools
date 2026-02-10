<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/api/_action/frosh-tools/shopware-operator', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopwareOperatorController extends AbstractController
{
    public function __construct(
        #[Autowire(value: '%env(default::SHOPWARE_OPERATOR_URL)%')]
        private readonly string $operatorUrl = '',
        private readonly HttpClientInterface $client
    ) {
    }

    #[Route(path: '/check', name: 'api.frosh.tools.shopware_operator.check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        if (empty($this->operatorUrl)) {
            return new JsonResponse(['message' => 'Shopware Operator URL is not configured'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = $this->client->request('GET', $this->operatorUrl);
            $content = $response->getContent();
            
            // Try to decode JSON to return it as a structured object, otherwise return raw string
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = ['content' => $content];
            }

            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
