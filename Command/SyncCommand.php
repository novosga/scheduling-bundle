<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle\Command;

use App\Repository\ORM\AgendamentoRepository;
use App\Repository\ORM\UnidadeRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\Agendamento;
use Novosga\Entity\Unidade;
use Novosga\SchedulingBundle\Dto\ServicoConfig;
use Novosga\SchedulingBundle\Dto\UnidadeConfig;
use Novosga\SchedulingBundle\Service\ApiClient;
use Novosga\SchedulingBundle\Service\SchedulingService;
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
class SyncCommand extends Command
{
    const MAX_DAYS = 7;

    protected static $defaultName = 'novosga:scheduling:sync';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var SchedulingService
     */
    private $service;

    /**
     * @var ApiClient
     */
    private $client;

    /**
     * @var UnidadeRepository
     */
    private $unidadeRepository;

    /**
     * @var AgendamentoRepository
     */
    private $agendamentoRepository;

    public function __construct(
        EntityManagerInterface $em,
        SchedulingService $service,
        ApiClient $client,
        UnidadeRepository $unidadeRepository,
        AgendamentoRepository $agendamentoRepository
    ) 
    {
        parent::__construct();
        $this->em = $em;
        $this->service = $service;
        $this->client = $client;
        $this->unidadeRepository = $unidadeRepository;
        $this->agendamentoRepository = $agendamentoRepository;
    }

    protected function configure()
    {
        $this
            ->setDescription('Sincroniza os agendamentos online');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Novo SGA Scheduling Sync');
        
        $this->syncLocalToRemove($io);
        $this->syncRemoteToLocal($io);

        return 0;
    }

    private function syncLocalToRemove($io)
    {
        $today = new DateTime();
        $today->setTime(0, 0, 0, 0);
        $limit = 100;
        $offset = 0;

        $query = $this
            ->agendamentoRepository
            ->createQueryBuilder('e')
            ->where('e.situacao = :situacao')
            ->andWhere('e.data >= :today')
            ->setParameter('situacao', Agendamento::SITUACAO_CONFIRMADO)
            ->setParameter('today', $today)
            ->setMaxResults($limit)
            ->getQuery();

        do {
            $agendamentos = $query
                ->setFirstResult($offset)
                ->getResult();
            
            /** @var Agendamento $agendamento */
            foreach ($agendamentos as $agendamento) {
                $io->text("Updating schedule ID {$agendamento->getId()}, date {$agendamento->getData()->format('Y-m-d')}");
                try {
                    $this->client->updateAgendamento(
                        $agendamento->getOid(),
                        Agendamento::SITUACAO_CONFIRMADO
                    );
                } catch (Throwable $ex) {
                    $io->error(sprintf(
                        "Failed to update remove schedule (OID: %s): %s",
                        $agendamento->getOid(),
                        $ex->getMessage()
                    ));
                }
            }

            $offset += count($agendamentos);
        } while (!empty($agendamentos));
    }

    private function syncRemoteToLocal(SymfonyStyle $io)
    {
        $unidades = $this->unidadeRepository->findAll();
        
        /** @var Unidade $unidade */
        foreach ($unidades as $unidade) {
            $unidadeConfig = $this->service->getUnidadeConfig($unidade);
            if ($unidadeConfig) {
                $io->section("Config found for unity {$unidade->getNome()}");
                $servicoConfigs = $this->service->getServicoConfigs($unidade);
                if (!count($servicoConfigs)) {
                    $io->info("No services config found");
                }
                foreach ($servicoConfigs as $servicoConfig) {
                    try {
                        $io->text("Syncing schedule for service {$servicoConfig->getServicoLocal()->getNome()} ... ");
                        $this->doSyncRemoteToLocal($io, $unidade, $unidadeConfig, $servicoConfig);
                    } catch (Throwable $e) {
                        $io->error($e->getMessage());
                    }
                }
            }
        }
    }

    private function doSyncRemoteToLocal(
        SymfonyStyle $io,
        Unidade $unidade,
        UnidadeConfig $unidadeConfig,
        ServicoConfig $servicoConfig
    ) {
        $total = 0;
        $totalSaved = 0;
        $startDate = new DateTime();
        $days = 0;
        $limit = 500;

        while ($days <= self::MAX_DAYS) {
            $date = clone $startDate;

            if ($days > 0) {
                $date->add(new DateInterval("P{$days}D"));
            }

            $offset = 0;

            do {
                $agendamentos = $this->client->getAgendamentos(
                    $servicoConfig->getServicoRemoto(),
                    $unidadeConfig->getUnidadeRemota(),
                    $date,
                    $limit,
                    $offset
                );
                $totalDay = count($agendamentos);
                $total += $totalDay;
                $offset += $totalDay;
                $io->text("Records found for date {$date->format('Y-m-d')}: {$totalDay}");

                foreach ($agendamentos as $value) {
                    $isAgendado = $value['situacao'] === 'agendado';
                    $isCancelado = !!$value['dataCancelamento'];
                    $isConfirmado = !!$value['dataConfirmacao'];
                    $oid = $value['id'];
                    $agendamento = $this->agendamentoRepository->findOneBy([
                        'oid' => $oid,
                    ]);
                    if ($isCancelado && $agendamento) {
                        $io->text("Cancelled record found. Removing it from local db.");

                        // remove agendamento que foi cancelado online
                        $this->em->remove($agendamento);
                        $this->em->flush();
                    }
                    if ($isConfirmado && $agendamento) {
                        $io->text("Confirmed record found. Updating it on local db.");

                        // atualiza agendamento confirmado
                        $dataConfirmacao = DateTime::createFromFormat('Y-m-d H:i:s', $value['dataConfirmacao']);
                        $agendamento->setDataConfirmacao($dataConfirmacao);
                        $this->em->persist($agendamento);
                        $this->em->flush();
                    }
                    if (!$isAgendado || $agendamento) {
                        $io->text("Record already synced found. Skipping.");
                        // pula agendamento confirmado/cancelado ou já sincronizado
                        continue;
                    }

                    $io->text("Persisting new record. Remote ID: {$oid}");
                    $agendamento = $this->service->toAgendamento($unidade, $servicoConfig->getServicoLocal(), $value);
                    $this->em->persist($agendamento);
                    $this->em->flush();
                    $totalSaved++;
                }

            } while ($totalDay >= $limit);
            $days++;
        }

        $io->text("Sync done. Total retrieved from API: {$total}. Total saved: {$totalSaved}");
    }
}
