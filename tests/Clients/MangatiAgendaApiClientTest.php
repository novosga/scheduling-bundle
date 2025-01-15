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

namespace Novosga\SchedulingBundle\Tests\Clients;

use DateTimeImmutable;
use Novosga\SchedulingBundle\Clients\MangatiAgendaApiClient;
use Novosga\SchedulingBundle\Clients\Dto\GetAgendamentosRequest;
use Novosga\SchedulingBundle\Clients\Dto\UnidadeRemota;
use Novosga\SchedulingBundle\Clients\Dto\ServicoRemoto;
use Novosga\SchedulingBundle\Clients\Dto\AgendamentoRemoto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * MangatiAgendaApiClientTest
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class MangatiAgendaApiClientTest extends TestCase
{
    private const TEST_ACCESS_TOKEN = 'api-token';
    private const TEST_API_URL = 'https://agenda.mangati.com/api';

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
    }

    public function testGetUnidades(): void
    {
        $httpClient = new MockHttpClient([
            function ($method, $url, $options): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://agenda.mangati.com/api/locations.json', $url);

                return new JsonMockResponse([
                    [
                        "id" => "018d3793-3352-7158-82a0-e3dac21465d2",
                        "name" => "Test name",
                        "description" => "test description",
                        "address" => [
                            "streetName" => "test street name",
                            "neighborhood" => "test neighborhood",
                            "streetNumber" => "100",
                            "postalCode" => "test postalcode",
                            "city" => "test city",
                            "state" => "test estate",
                        ]
                    ],
                ]);
            }
        ]);
        $apiClient = new MangatiAgendaApiClient(
            $httpClient,
            $this->logger,
            self::TEST_ACCESS_TOKEN,
            self::TEST_API_URL,
        );

        $unidades = $apiClient->getUnidades();

        $this->assertCount(1, $unidades);
        $this->assertInstanceOf(UnidadeRemota::class, $unidades[0]);
        $this->assertEquals('018d3793-3352-7158-82a0-e3dac21465d2', $unidades[0]->id);
        $this->assertEquals('Test name', $unidades[0]->nome);
    }

    public function testGetServicos(): void
    {
        $httpClient = new MockHttpClient([
            function ($method, $url, $options): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://agenda.mangati.com/api/resources.json', $url);

                return new JsonMockResponse([
                    [
                        "id" => "018d3793-4f8f-7f44-a6e6-a48ba630646b",
                        "name" => "Test name",
                    ],
                ]);
            }
        ]);
        $apiClient = new MangatiAgendaApiClient(
            $httpClient,
            $this->logger,
            self::TEST_ACCESS_TOKEN,
            self::TEST_API_URL,
        );

        $servicos = $apiClient->getServicos();

        $this->assertCount(1, $servicos);
        $this->assertInstanceOf(ServicoRemoto::class, $servicos[0]);
        $this->assertEquals('018d3793-4f8f-7f44-a6e6-a48ba630646b', $servicos[0]->id);
        $this->assertEquals('Test name', $servicos[0]->nome);
    }

    public function testGetAgendamentos(): void
    {
        $httpClient = new MockHttpClient([
            function ($method, $url, $options): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertStringStartsWith('https://agenda.mangati.com/api/appointments.json?', $url);
                $this->assertSame(1, $options['query']['page']);
                $this->assertSame('2025-01-15', $options['query']['date']);
                $this->assertSame('018d3793-4f8f-7f44-a6e6-a48ba630646b', $options['query']['resource']);
                $this->assertSame('018d3793-3352-7158-82a0-e3dac21465d2', $options['query']['location']);

                return new JsonMockResponse([
                    [
                        "id" => "01946af7-f075-71f0-ac6a-6072aaa624a1",
                        "name" => "Test name",
                        "email" => "test@email.com",
                        "status" => "scheduled",
                        "date" => "2025-01-15",
                        "time" => "15:00",
                        "resource" => [
                            "id" => "018d3793-4f8f-7f44-a6e6-a48ba630646b",
                            "name" => "Test resource"
                        ],
                        "location" => [
                            "id" => "018d3793-3352-7158-82a0-e3dac21465d2",
                            "name" => "Test location",
                            "description" => "desc",
                            "address" => [
                                "streetName" => "test street name",
                                "neighborhood" => "test neighborhood",
                                "streetNumber" => "100",
                                "postalCode" => "test postalcode",
                                "city" => "test city",
                                "state" => "test estate",
                            ]
                        ],
                        "phone" => "27999991111",
                    ],
                ]);
            }
        ]);
        $apiClient = new MangatiAgendaApiClient(
            $httpClient,
            $this->logger,
            self::TEST_ACCESS_TOKEN,
            self::TEST_API_URL,
        );

        $request = new GetAgendamentosRequest(
            date: DateTimeImmutable::createFromFormat('Y-m-d', '2025-01-15'),
            servicoId: '018d3793-4f8f-7f44-a6e6-a48ba630646b',
            unidadeId: '018d3793-3352-7158-82a0-e3dac21465d2',
        );
        $agendamentos = $apiClient->getAgendamentos($request);

        $this->assertCount(1, $agendamentos);
        $this->assertInstanceOf(AgendamentoRemoto::class, $agendamentos[0]);
        $this->assertEquals('01946af7-f075-71f0-ac6a-6072aaa624a1', $agendamentos[0]->id);
        $this->assertEquals('Test name', $agendamentos[0]->nome);
        $this->assertEquals('27999991111', $agendamentos[0]->telefone);
        $this->assertEquals('test@email.com', $agendamentos[0]->email);
        $this->assertEquals('agendado', $agendamentos[0]->situacao);
    }
}
