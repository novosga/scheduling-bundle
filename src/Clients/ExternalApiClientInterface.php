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

use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use Novosga\SchedulingBundle\Clients\Dto\ServicoRemoto;
use Novosga\SchedulingBundle\Clients\Dto\UnidadeRemota;
use Novosga\SchedulingBundle\Clients\Dto\GetAgendamentosRequest;

/**
 * ExternalApiClientInterface
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
interface ExternalApiClientInterface
{
    /** @return UnidadeRemota[] */
    public function getUnidades(): array;

    /** @return ServicoRemoto[] */
    public function getServicos(): array;

    /** @return AgendamentoRemoto[] */
    public function getAgendamentos(GetAgendamentosRequest $request): array;

    public function updateAgendamento(int|string $agendamentoId, string $situacao): bool;
}
