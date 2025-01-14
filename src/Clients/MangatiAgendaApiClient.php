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

use DateTime;
use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use Novosga\SchedulingBundle\Clients\Dto\GetAgendamentosRequest;
use Novosga\SchedulingBundle\Clients\Dto\ServicoRemoto;
use Novosga\SchedulingBundle\Clients\Dto\UnidadeRemota;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MangatiAgendaApiClient
 * Classe cliente da API do sistema Mangati Agenda
 * {@link https://agenda.mangati.com/}
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class MangatiAgendaApiClient implements ExternalApiClientInterface
{
    use ExternalApiClientTrait;

    /** @var array<string,string> */
    private $statusDict;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $apiToken,
        private string $apiUrl,
    ) {
        if (!str_ends_with($this->apiUrl, '/')) {
            $this->apiUrl .= '/';
        }
        $this->statusDict = [
            'scheduled' => 'agendado',
            'cancelled' => 'cancelado',
            'completed' => 'confirmado',
            'no_show' => 'nao_compareceu',
            'deleted' => 'excluido',
        ];
    }

    /** @return UnidadeRemota[] */
    public function getUnidades(): array
    {
        $unidades = [];
        $response = $this->request('GET', "{$this->apiUrl}locations.json");

        if ($response) {
            $unidades = array_map(function ($row) {
                return new UnidadeRemota(
                    id: $row['id'],
                    nome: $row['name'],
                );
            }, $response);
        }

        return $unidades;
    }

    /** @return ServicoRemoto[] */
    public function getServicos(): array
    {
        $servicos = [];
        $response = $this->request('GET', "{$this->apiUrl}resources.json");

        if ($response) {
            $servicos = array_map(function ($row) {
                return new ServicoRemoto(
                    id: $row['id'],
                    nome: $row['name'],
                );
            }, $response);
        }

        return $servicos;
    }

    /** @return AgendamentoRemoto[] */
    public function getAgendamentos(GetAgendamentosRequest $request): array
    {
        $date = $request->date;
        if (!$date) {
            $date = new DateTime();
        }
        $response = $this->request('GET', "{$this->apiUrl}appointments.json", [
            'date' => $date->format('Y-m-d'),
            'page' => $request->page,
            'resource' => $request->servicoId,
            'location' => $request->unidadeId,
        ]);

        $agendamentos = array_map(function ($row) {
            $status = $this->statusDict[$row['status']] ?? $row['status'];

            return new AgendamentoRemoto(
                id: $row['id'],
                nome: $row['name'],
                situacao: $status,
                dataCancelamento: null,
                dataConfirmacao: null,
                documento: $row['documentId'] ?? '',
                data: $row['date'],
                horaInicio: $row['time'],
                email: $row['email'] ?? '',
                telefone: $row['phone'] ?? '',
            );
        }, $response);

        return $agendamentos;
    }

    public function updateAgendamento(int|string $agendamentoId, string $situacao): bool
    {
        // TODO
        $this->logger->info('updateAgendamento not implemented');
        return false;
    }
}
