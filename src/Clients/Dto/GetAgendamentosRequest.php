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

namespace Novosga\SchedulingBundle\Clients\Dto;

use DateTimeImmutable;

/**
 * GetAgendamentosRequest
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class GetAgendamentosRequest
{
    public function __construct(
        public readonly string|int|null $servicoId = null,
        public readonly string|int|null $unidadeId = null,
        public readonly ?DateTimeImmutable $date = null,
        public readonly int $page = 1
    ) {
    }
}
