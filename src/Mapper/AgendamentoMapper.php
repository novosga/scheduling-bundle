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

use DateTime;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\ServicoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use Novosga\Service\AgendamentoServiceInterface;

/**
 * AgendamentoMapper
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class AgendamentoMapper
{
    public function __construct(
        private readonly ClienteMapper $clienteMapper,
        private readonly AgendamentoServiceInterface $agendamentoService,
    ) {
    }

    public function toAgendamento(
        UnidadeInterface $unidade,
        ServicoInterface $servico,
        AgendamentoRemoto $agendamento
    ): AgendamentoInterface {
        // 'data' => $this->data->format('Y-m-d'),
        $data = DateTime::createFromFormat('Y-m-d', $agendamento->data);
        $hora = DateTime::createFromFormat('H:i', $agendamento->horaInicio);

        $oid = $agendamento->id;
        $cliente = $this->clienteMapper->toCliente($agendamento);

        $agendamento = $this->agendamentoService->build();
        $agendamento
            ->setOid($oid)
            ->setServico($servico)
            ->setUnidade($unidade)
            ->setData($data)
            ->setHora($hora)
            ->setCliente($cliente);

        return $agendamento;
    }
}
