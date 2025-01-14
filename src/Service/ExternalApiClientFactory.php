<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle\Service;

use Exception;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Novosga\SchedulingBundle\Clients\ExternalApiClientInterface;
use Novosga\SchedulingBundle\Clients\MangatiAgendaApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalApiClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(UnidadeConfig $config): ExternalApiClientInterface
    {
        return $this->createFromArgs(
            $config->provider,
            $config->apiUrl,
            $config->accessToken,
        );
    }

    public function createFromArgs(string $provider, string $apiUrl, string $accessToken): ExternalApiClientInterface
    {
        return match ($provider) {
            "mangati.agenda" => new MangatiAgendaApiClient(
                $this->client,
                $this->logger,
                $accessToken,
                $apiUrl,
            ),
            default => throw new Exception('Invalid scheduling provider: ' . $provider),
        };
    }
}
