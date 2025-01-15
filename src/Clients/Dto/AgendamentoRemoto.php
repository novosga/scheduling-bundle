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
 * AgendamentoRemoto
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class AgendamentoRemoto
{
    public function __construct(
        public readonly int|string|null $id = null,
        public readonly ?string $nome = null,
        public readonly ?string $situacao = null,
        public readonly ?DateTimeImmutable $dataCancelamento = null,
        public readonly ?DateTimeImmutable $dataConfirmacao = null,
        public readonly ?string $documento = null,
        public readonly ?string $data = null,
        public readonly ?string $horaInicio = null,
        public readonly ?string $email = null,
        public readonly ?string $telefone = null
    ) {
    }
}
