<?php

namespace Novosga\SchedulingBundle\Service;

use DateTime;
use Novosga\SchedulingBundle\Dto\ServicoRemoto;
use Novosga\SchedulingBundle\Dto\UnidadeRemota;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class ApiClient
{
    /**
     * @var HttpClientInterface
     */
    private $wrapped;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $apiToken;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $apiUrl, string $apiToken)
    {
        $this->wrapped = $client;
        $this->apiUrl = $apiUrl;
        $this->apiToken = $apiToken;
        $this->logger = $logger;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function request(string $method, $url, array $query = [], array $body = []): ?ResponseInterface
    {
        try {
            $response = $this->wrapped->request($method, $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                ],
                'query' => $query,
                'body' => $body,
            ]);
            //$statusCode = $response->getStatusCode();

            return $response;
        } catch (Throwable $ex) {
            $this->logger->error('Error trying to access remote API: ' . $url);
            $this->logger->error($ex->getMessage());
        }

        return null;
    }

    /**
     * @return UnidadeRemota[]
     */
    public function getUnidades(): array
    {
        $unidades = [];
        $response = $this->request('GET', "{$this->apiUrl}/unidades");

        if ($response) {
            $unidades = array_map(function ($row) {
                return new UnidadeRemota($row['id'], $row['nome']);
            }, $response->toArray());
        }

        return $unidades;
    }

    /**
     * @return ServicoRemoto[]
     */
    public function getServicos(): array
    {
        $servicos = [];
        $response = $this->request('GET', "{$this->apiUrl}/servicos");

        if ($response) {
            $servicos = array_map(function ($row) {
                return new ServicoRemoto($row['id'], $row['nome']);
            }, $response->toArray());
        }

        return $servicos;
    }

    public function getAgendamentos($servicoId, $unidadeId, $date = null, $limit = 500, $offset = 0): array
    {
        if (!$date) {
            $date = new DateTime();
        }
        $response = $this->request('GET', "{$this->apiUrl}/servicos/{$servicoId}/unidades/{$unidadeId}/agendamentos", [
            'data' => $date->format('Y-m-d'),
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $response ? $response->toArray() : [];
    }

    public function updateAgendamento($agendamentoId, $situacao): bool
    {
        $response = $this->request('PUT', "{$this->apiUrl}/agendamentos/${agendamentoId}", [], [
            'situacao' => $situacao,
        ]);

        return $response ? $response->getStatusCode() === 200 : false;
    }
}
