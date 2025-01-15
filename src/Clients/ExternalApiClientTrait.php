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

namespace Novosga\SchedulingBundle\Clients;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ExternalApiClientTrait
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
trait ExternalApiClientTrait
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, array $query = [], array $body = []): array
    {
        try {
            /** @var ResponseInterface */
            $response = $this->client->request($method, $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                ],
                'query' => $query,
                'body' => $body,
            ]);

            return $response->toArray();
        } catch (ClientExceptionInterface $ex) {
            $this->logger->error(sprintf(
                'Error trying to access remote API: %s. Error: %s',
                $url,
                $ex->getMessage(),
            ));
            return [];
        }
    }
}
