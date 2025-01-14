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

use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;

/**
 * UnidadeConfigMapper
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class UnidadeConfigMapper
{
    /** @param array<string,mixed> $value */
    public function toUnidadeConfig(array $value): UnidadeConfig
    {
        return new UnidadeConfig(
            unidadeRemota: $value['unidadeRemota'],
            apiUrl: $value['apiUrl'],
            provider: $value['provider'],
            accessToken: $value['accessToken'],
        );
    }
}
