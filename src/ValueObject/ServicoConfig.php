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
use Novosga\Entity\ServicoInterface;

/**
 * ServicoConfig
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ServicoConfig implements JsonSerializable
{
    public function __construct(
        public ?ServicoInterface $servicoLocal = null,
        public int|string|null $servicoRemoto = null
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'servicoLocal' => $this->servicoLocal?->getId(),
            'servicoRemoto' => $this->servicoRemoto,
        ];
    }
}
