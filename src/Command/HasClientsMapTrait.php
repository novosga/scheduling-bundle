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

namespace Novosga\SchedulingBundle\Command;

use Novosga\Entity\UnidadeInterface;
use Novosga\SchedulingBundle\Clients\ExternalApiClientInterface;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;

/**
 * HasClientsMapTrait
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
trait HasClientsMapTrait
{
    /** @var array<int,ExternalApiClientInterface> */
    private array $clientsMap = [];

    private function getClient(UnidadeInterface $unidade, UnidadeConfig $unidadeConfig): ExternalApiClientInterface
    {
        $client = $this->clientsMap[$unidade->getId()] ?? null;

        if (!$client) {
            $client = $this->clientFactory->create($unidadeConfig);
            $this->clientsMap[$unidade->getId()] = $client;
        }

        return $client;
    }
}
