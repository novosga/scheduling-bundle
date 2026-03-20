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

namespace Novosga\SchedulingBundle\Command;

use DateInterval;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Repository\AgendamentoRepositoryInterface;
use Novosga\Repository\UnidadeRepositoryInterface;
use Novosga\SchedulingBundle\Clients\Dto\GetAgendamentosRequest;
use Novosga\SchedulingBundle\Service\AppointmentService;
use Novosga\SchedulingBundle\Service\ConfigService;
use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * SyncCommand
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[AsCommand(
    name: 'novosga:scheduling:sync',
    description: 'Sincroniza os agendamentos online'
)]
class SyncCommand extends Command
{
    use HasClientsMapTrait;

    private const MAX_DAYS = 7;

    public function __construct(
        private readonly ConfigService $configService,
        private readonly AppointmentService $appointmentService,
        private readonly ExternalApiClientFactory $clientFactory,
        private readonly UnidadeRepositoryInterface $unidadeRepository,
        private readonly AgendamentoRepositoryInterface $agendamentoRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Novo SGA Scheduling Sync');

        /** @var UnidadeInterface[] */
        $unidades = $this->unidadeRepository->findBy([
            'ativo' => true,
        ]);

        foreach ($unidades as $unidade) {
            $io->info(sprintf(
                '[unidade-%s] Syncing appointments for Unit %s',
                $unidade->getId(),
                $unidade->getNome(),
            ));

            $this->syncLocalToRemove($unidade, $io);
            $this->syncRemoteToLocal($unidade, $io);
        }

        return Command::SUCCESS;
    }

    private function syncLocalToRemove(UnidadeInterface $unidade, SymfonyStyle $io): void
    {
        $io->text(sprintf("[unidade-%s] Updating remote from local", $unidade->getId()));

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
            ->setParameter('today', $today)
            ->setMaxResults($limit)
            ->getQuery();

        do {
            $agendamentos = $query
                ->setFirstResult($offset)
                ->getResult();

            /** @var AgendamentoInterface $agendamento */
            foreach ($agendamentos as $agendamento) {
                $io->text(sprintf(
                    "Updating appointment ID %s, date %s",
                    $agendamento->getId(),
                    $agendamento->getData()->format('Y-m-d'),
                ));

                $unidadeConfig = $this->configService->getUnidadeConfig($agendamento->getUnidade());
                if (!$unidadeConfig) {
                    continue;
                }
                $client = $this->getClient($agendamento->getUnidade(), $unidadeConfig);

                try {
                    $client->updateAgendamento(
                        $agendamento->getOid(),
                        AgendamentoInterface::SITUACAO_CONFIRMADO
                    );
                } catch (Throwable $ex) {
                    $io->error(sprintf(
                        "Failed to update remove appointment (OID: %s): %s",
                        $agendamento->getOid(),
                        $ex->getMessage(),
                    ));
                }
            }

            $offset += count($agendamentos);
        } while (!empty($agendamentos));
    }

    private function syncRemoteToLocal(UnidadeInterface $unidade, SymfonyStyle $io): void
    {
        $io->text(sprintf("[unidade-%s] Updating local remote", $unidade->getId()));

        $unidadeConfig = $this->configService->getUnidadeConfig($unidade);
        if ($unidadeConfig) {
            $io->section("Config found for unity {$unidade->getNome()}");
            $servicoConfigs = $this->configService->getServicoConfigs($unidade);
            if (!count($servicoConfigs)) {
                $io->info("No services config found");
            }
            foreach ($servicoConfigs as $servicoConfig) {
                try {
                    $io->text("Syncing appointment for service {$servicoConfig->servicoLocal->getNome()} ... ");
                    $this->doSyncRemoteToLocal($io, $unidade, $unidadeConfig, $servicoConfig);
                } catch (Throwable $e) {
                    $io->error($e->getMessage());
                }
            }
        }
    }

    private function doSyncRemoteToLocal(
        SymfonyStyle $io,
        UnidadeInterface $unidade,
        UnidadeConfig $unidadeConfig,
        ServicoConfig $servicoConfig,
    ): void {
        $total = 0;
        $totalSaved = 0;
        $startDate = $this->clock->now()->setTimezone($unidade->getDateTimeZone());
        $days = 0;
        $client = $this->getClient($unidade, $unidadeConfig);

        while ($days <= self::MAX_DAYS) {
            $date = $startDate;
            if ($days > 0) {
                $date = $date->add(new DateInterval("P{$days}D"));
            }

            $page = 1;

            do {
                $agendamentos = $client->getAgendamentos(new GetAgendamentosRequest(
                    servicoId: $servicoConfig->servicoRemoto,
                    unidadeId: $unidadeConfig->unidadeRemota,
                    date: $date,
                    page: $page,
                ));
                $totalDay = count($agendamentos);
                $io->text("Records found for date {$date->format('Y-m-d')} (page={$page}): {$totalDay}");

                $total += $totalDay;
                $page++;

                foreach ($agendamentos as $remoto) {
                    $isAgendado = $remoto->situacao === 'agendado';
                    $isCancelado = !!$remoto->dataCancelamento;
                    $isConfirmado = !!$remoto->dataConfirmacao;
                    $oid = $remoto->id;
                    $agendamento = $this->agendamentoRepository->findOneBy([
                        'oid' => $oid,
                    ]);
                    if ($isCancelado && $agendamento) {
                        $io->text("Cancelled record found. Removing it from local db.");
                        // remove agendamento que foi cancelado online
                        $this->appointmentService->remove($agendamento);
                    }
                    if ($isConfirmado && $agendamento) {
                        $io->text("Confirmed record found. Updating it on local db.");

                        // atualiza agendamento confirmado
                        $this->appointmentService->markAsDone($agendamento, $remoto);
                    }
                    if (!$isAgendado) {
                        $io->text("Remote appoitment is already done. Skipping.");
                        // pula agendamento confirmado/cancelado
                        continue;
                    }
                    if ($agendamento) {
                        $io->text("Record already synced found. Updating customer info.");
                        // agendamento já sincronizado, atualiza cliente e pula
                        $this->appointmentService->updateCliente($agendamento->getCliente(), $remoto);
                        continue;
                    }

                    $io->text("Persisting new record. Remote ID: {$oid}");
                    $this->appointmentService->save($unidade, $servicoConfig->servicoLocal, $remoto);
                    $totalSaved++;
                }
            } while (count($agendamentos));
            $days++;
        }

        $io->text("Sync done. Total items retrieved from API: {$total}. Total items saved: {$totalSaved}");
    }
}
