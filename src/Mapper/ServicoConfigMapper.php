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

namespace Novosga\SchedulingBundle\Mapper;

use Novosga\Repository\ServicoRepositoryInterface;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;

/**
 * ServicoConfigMapper
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ServicoConfigMapper
{
    public function __construct(
        private readonly ServicoRepositoryInterface $servicoRepository,
    ) {
    }

    /** @param array<string,mixed> $value */
    public function toServicoConfig(array $value): ServicoConfig
    {
        $servicoLocal = $this->servicoRepository->find($value['servicoLocal']);

        return new ServicoConfig(
            servicoLocal: $servicoLocal,
            servicoRemoto: $value['servicoRemoto'],
        );
    }
}
