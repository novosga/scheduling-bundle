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

namespace Novosga\SchedulingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\ClienteInterface;
use Novosga\Entity\ServicoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use Novosga\SchedulingBundle\Mapper\AgendamentoMapper;
use Novosga\SchedulingBundle\Mapper\ClienteMapper;

/**
 * AppointmentService
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class AppointmentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendamentoMapper $agendamentoMapper,
        private readonly ClienteMapper $clienteMapper,
    ) {
    }

    public function save(UnidadeInterface $unidade, ServicoInterface $servico, AgendamentoRemoto $remoto): void
    {
        $agendamento = $this->agendamentoMapper->toAgendamento($unidade, $servico, $remoto);
        $this->em->persist($agendamento);
        $this->em->flush();
    }

    public function remove(AgendamentoInterface $agendamento): void
    {
        $this->em->remove($agendamento);
        $this->em->flush();
    }

    public function markAsDone(AgendamentoInterface $agendamento, AgendamentoRemoto $remoto): void
    {
        $agendamento->setDataConfirmacao($remoto->dataConfirmacao);
        $this->em->persist($agendamento);
        $this->em->flush();
    }

    public function updateCliente(ClienteInterface $cliente, AgendamentoRemoto $remoto): void
    {
        $cliente = $this->clienteMapper->updateCliente($cliente, $remoto);
        $this->em->persist($cliente);
        $this->em->flush();
    }
}
