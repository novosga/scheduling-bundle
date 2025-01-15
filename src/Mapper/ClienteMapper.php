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

use Novosga\Entity\ClienteInterface;
use Novosga\Repository\ClienteRepositoryInterface;
use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use Novosga\Service\ClienteServiceInterface;

/**
 * ClienteMapper
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ClienteMapper
{
    public function __construct(
        private readonly ClienteServiceInterface $clienteService,
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {
    }

    public function toCliente(AgendamentoRemoto $agendamento): ClienteInterface
    {
        $documento = $agendamento->documento ?? '';
        $clientes = $this->clienteRepository->findByDocumento($documento);
        if (count($clientes)) {
            return $this->updateCliente($clientes[0], $agendamento);
        }

        return $this->clienteService
            ->build()
            ->setNome($agendamento->nome)
            ->setDocumento($agendamento->documento)
            ->setEmail($agendamento->email)
            ->setTelefone($agendamento->telefone);
    }

    public function updateCliente(ClienteInterface $cliente, AgendamentoRemoto $agendamento): ClienteInterface
    {
        return $cliente
            ->setNome($agendamento->nome ?? $cliente->getNome())
            ->setDocumento($agendamento->documento ?? $cliente->getDocumento())
            ->setEmail($agendamento->email ?? $cliente->getEmail())
            ->setTelefone($agendamento->telefone ?? $cliente->getTelefone());
    }
}
