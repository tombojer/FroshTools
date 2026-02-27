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
        private readonly ?string $operatorUrl = null,
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

            return new JsonResponse($this->parsePrometheusMetrics($content));
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function parsePrometheusMetrics(string $content): array
    {
        $stores = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_starts_with($line, 'shopware_store_')) {
                continue;
            }

            if (!preg_match('/^(\w+)\{([^}]*)\}\s+([\d.e+\-]+)$/', $line, $matches)) {
                continue;
            }

            $metricName = $matches[1];
            $value = (float) $matches[3];

            $labels = [];
            preg_match_all('/(\w+)="([^"]*)"/', $matches[2], $labelMatches, PREG_SET_ORDER);
            foreach ($labelMatches as $labelMatch) {
                $labels[$labelMatch[1]] = $labelMatch[2];
            }

            $storeName = $labels['store'] ?? 'unknown';

            if (!isset($stores[$storeName])) {
                $stores[$storeName] = [
                    'name' => $storeName,
                    'namespace' => $labels['namespace'] ?? '',
                    'state' => 'unknown',
                    'currentImage' => '',
                    'usageDataConsent' => false,
                    'deployments' => [],
                    'hpa' => [
                        'enabled' => false,
                        'minReplicas' => 0,
                        'maxReplicas' => 0,
                    ],
                    'scheduledTask' => [
                        'lastRunStatus' => 0,
                        'lastSuccessTimestamp' => null,
                        'suspended' => false,
                    ],
                ];
            }

            $intValue = (int) $value;

            switch ($metricName) {
                case 'shopware_store_state':
                    if ($intValue === 1) {
                        $stores[$storeName]['state'] = $labels['state'] ?? 'unknown';
                    }
                    break;
                case 'shopware_store_current_image':
                    if ($intValue === 1) {
                        $stores[$storeName]['currentImage'] = $labels['image'] ?? '';
                    }
                    break;
                case 'shopware_store_usage_data_consent':
                    $stores[$storeName]['usageDataConsent'] = $intValue === 1;
                    break;
                case 'shopware_store_deployment_state':
                    if ($intValue === 1) {
                        $type = $labels['deployment_type'] ?? 'unknown';
                        $stores[$storeName]['deployments'][$type]['state'] = $labels['state'] ?? 'unknown';
                    }
                    break;
                case 'shopware_store_deployment_replicas_available':
                    $type = $labels['deployment_type'] ?? 'unknown';
                    $stores[$storeName]['deployments'][$type]['availableReplicas'] = $intValue;
                    break;
                case 'shopware_store_deployment_replicas_desired':
                    $type = $labels['deployment_type'] ?? 'unknown';
                    $stores[$storeName]['deployments'][$type]['desiredReplicas'] = $intValue;
                    break;
                case 'shopware_store_hpa_enabled':
                    $stores[$storeName]['hpa']['enabled'] = $intValue === 1;
                    break;
                case 'shopware_store_hpa_min_replicas':
                    $stores[$storeName]['hpa']['minReplicas'] = $intValue;
                    break;
                case 'shopware_store_hpa_max_replicas':
                    $stores[$storeName]['hpa']['maxReplicas'] = $intValue;
                    break;
                case 'shopware_store_scheduled_task_last_run_status':
                    $stores[$storeName]['scheduledTask']['lastRunStatus'] = $intValue;
                    break;
                case 'shopware_store_scheduled_task_last_success_timestamp':
                    $stores[$storeName]['scheduledTask']['lastSuccessTimestamp'] = $intValue > 0 ? $intValue : null;
                    break;
                case 'shopware_store_scheduled_task_suspended':
                    $stores[$storeName]['scheduledTask']['suspended'] = $intValue === 1;
                    break;
            }
        }

        return ['stores' => array_values($stores)];
    }
}
