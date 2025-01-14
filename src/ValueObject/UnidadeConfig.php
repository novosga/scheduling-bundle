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

namespace Novosga\SchedulingBundle\ValueObject;

use JsonSerializable;

/**
 * UnidadeConfig
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class UnidadeConfig implements JsonSerializable
{
    public function __construct(
        public int|string|null $unidadeRemota = null,
        public ?string $apiUrl = null,
        public ?string $accessToken = null,
        public ?string $provider = null
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'unidadeRemota' => $this->unidadeRemota,
            'apiUrl' => $this->apiUrl,
            'accessToken' => $this->accessToken,
            'provider' => $this->provider,
        ];
    }
}
