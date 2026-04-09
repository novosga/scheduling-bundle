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

use DateInterval;
use Doctrine\DBAL\Types\Types;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Repository\AgendamentoRepositoryInterface;
use Novosga\SchedulingBundle\Clients\Dto\GetAgendamentosRequest;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * SyncService
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class SyncService
{
    private const MAX_DAYS = 7;

    public function __construct(
        private readonly ConfigService $configService,
        private readonly AppointmentService $appointmentService,
        private readonly ExternalApiClientFactory $clientFactory,
        private readonly AgendamentoRepositoryInterface $agendamentoRepository,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Syncs appointments for a single unidade.
     *
     * @return array{total: int, saved: int, errors: list<string>}
     */
    public function syncUnidade(UnidadeInterface $unidade): array
    {
        $result = ['total' => 0, 'saved' => 0, 'errors' => []];

        $this->syncLocalToRemote($unidade, $result);
        $this->syncRemoteToLocal($unidade, $result);

        return $result;
    }

    /** @param array{total: int, saved: int, errors: list<string>} $result */
    private function syncLocalToRemote(UnidadeInterface $unidade, array &$result): void
    {
        $this->logger->info('[unidade-{id}] Updating remote from local', ['id' => $unidade->getId()]);

        $today = $this->clock->now()->setTimezone($unidade->getDateTimeZone());
        $limit = 100;
        $offset = 0;

        $query = $this
            ->agendamentoRepository
            ->createQueryBuilder('e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.situacao = :situacao')
            ->andWhere('e.data >= :today')
            ->setParameter('unidade', $unidade->getId())
            ->setParameter('situacao', AgendamentoInterface::SITUACAO_CONFIRMADO)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery();

        do {
            $agendamentos = $query
                ->setFirstResult($offset)
                ->getResult();

            /** @var AgendamentoInterface $agendamento */
            foreach ($agendamentos as $agendamento) {
                $this->logger->debug('Updating appointment ID {id}, date {date}', [
                    'id' => $agendamento->getId(),
                    'date' => $agendamento->getData()->format('Y-m-d'),
                ]);

                $unidadeConfig = $this->configService->getUnidadeConfig($agendamento->getUnidade());
                if (!$unidadeConfig) {
                    continue;
                }

                $client = $this->clientFactory->create($unidadeConfig);

                try {
                    $client->updateAgendamento(
                        $agendamento->getOid(),
                        AgendamentoInterface::SITUACAO_CONFIRMADO,
                    );
                } catch (Throwable $ex) {
                    $message = sprintf(
                        'Failed to update remote appointment (OID: %s): %s',
                        $agendamento->getOid(),
                        $ex->getMessage(),
                    );
                    $this->logger->error($message);
                    $result['errors'][] = $message;
                }
            }

            $offset += count($agendamentos);
        } while (!empty($agendamentos));
    }

    /** @param array{total: int, saved: int, errors: list<string>} $result */
    private function syncRemoteToLocal(UnidadeInterface $unidade, array &$result): void
    {
        $this->logger->info('[unidade-{id}] Updating local from remote', ['id' => $unidade->getId()]);

        $unidadeConfig = $this->configService->getUnidadeConfig($unidade);
        if (!$unidadeConfig) {
            return;
        }

        $servicoConfigs = $this->configService->getServicoConfigs($unidade);
        if (empty($servicoConfigs)) {
            $this->logger->info('[unidade-{id}] No service configs found', ['id' => $unidade->getId()]);
            return;
        }

        foreach ($servicoConfigs as $servicoConfig) {
            try {
                $this->logger->info('Syncing appointments for service {service}', [
                    'service' => $servicoConfig->servicoLocal->getNome(),
                ]);
                $this->doSyncRemoteToLocal($unidade, $unidadeConfig, $servicoConfig, $result);
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage());
                $result['errors'][] = $e->getMessage();
            }
        }
    }

    /** @param array{total: int, saved: int, errors: list<string>} $result */
    private function doSyncRemoteToLocal(
        UnidadeInterface $unidade,
        UnidadeConfig $unidadeConfig,
        ServicoConfig $servicoConfig,
        array &$result,
    ): void {
        $startDate = $this->clock->now()->setTimezone($unidade->getDateTimeZone());
        $client = $this->clientFactory->create($unidadeConfig);

        for ($days = 0; $days <= self::MAX_DAYS; $days++) {
            $date = $days > 0
                ? $startDate->add(new DateInterval("P{$days}D"))
                : $startDate;

            $page = 1;

            do {
                $agendamentos = $client->getAgendamentos(new GetAgendamentosRequest(
                    servicoId: $servicoConfig->servicoRemoto,
                    unidadeId: $unidadeConfig->unidadeRemota,
                    date: $date,
                    page: $page,
                ));
                $totalDay = count($agendamentos);
                $this->logger->debug('Records for date {date} (page={page}): {count}', [
                    'date' => $date->format('Y-m-d'),
                    'page' => $page,
                    'count' => $totalDay,
                ]);

                $result['total'] += $totalDay;
                $page++;

                foreach ($agendamentos as $remoto) {
                    $isAgendado = $remoto->situacao === 'agendado';
                    $isCancelado = (bool) $remoto->dataCancelamento;
                    $isConfirmado = (bool) $remoto->dataConfirmacao;
                    $oid = $remoto->id;

                    $agendamento = $this->agendamentoRepository->findOneBy(['oid' => $oid]);

                    if ($isCancelado && $agendamento) {
                        $this->logger->debug('Cancelled record found. Removing from local db.');
                        $this->appointmentService->remove($agendamento);
                        continue;
                    }
                    if ($isConfirmado && $agendamento) {
                        $this->logger->debug('Confirmed record found. Updating on local db.');
                        $this->appointmentService->markAsDone($agendamento, $remoto);
                    }
                    if (!$isAgendado) {
                        $this->logger->debug('Remote appointment is already done. Skipping.');
                        continue;
                    }
                    if ($agendamento) {
                        $this->logger->debug('Record already synced. Updating customer info.');
                        $this->appointmentService->updateCliente($agendamento->getCliente(), $remoto);
                        continue;
                    }

                    $this->logger->debug('Persisting new record. Remote ID: {oid}', ['oid' => $oid]);
                    $this->appointmentService->save($unidade, $servicoConfig->servicoLocal, $remoto);
                    $result['saved']++;
                }
            } while (!empty($agendamentos));
        }

        $this->logger->info('Sync done. Total: {total}, Saved: {saved}', [
            'total' => $result['total'],
            'saved' => $result['saved'],
        ]);
    }
}
