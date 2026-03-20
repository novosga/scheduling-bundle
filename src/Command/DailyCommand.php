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

use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Repository\AgendamentoRepositoryInterface;
use Novosga\Repository\UnidadeRepositoryInterface;
use Novosga\SchedulingBundle\Service\ConfigService;
use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * DailyCommand
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[AsCommand(
    name: 'novosga:scheduling:daily',
    description: 'Atualiza a situação dos agendamentos como nao_compareceu',
)]
class DailyCommand extends Command
{
    use HasClientsMapTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConfigService $configService,
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
        $io->title('Novo SGA Scheduling Daily');


        /** @var UnidadeInterface[] */
        $unidades = $this->unidadeRepository->findBy([
            'ativo' => true,
        ]);

        foreach ($unidades as $unidade) {
            $io->info(sprintf(
                '[unidade-%s] Checking appointments for Unit %s',
                $unidade->getId(),
                $unidade->getNome(),
            ));

            $this->handleUnidade($unidade, $io);
        }

        return Command::SUCCESS;
    }

    private function handleUnidade(UnidadeInterface $unidade, SymfonyStyle $io): void
    {
        $today = $this->clock->now()->setTimezone($unidade->getDateTimeZone());
        $limit = 100;
        $offset = 0;

        $io->text(sprintf(
            '[unidade-%s] Current unit datetime: %s',
            $unidade->getId(),
            $today->format('Y-m-d H:i:s'),
        ));

        $query = $this
            ->agendamentoRepository
            ->createQueryBuilder('e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.situacao = :situacao')
            ->andWhere('e.data < :today')
            ->setParameter('unidade', $unidade->getId())
            ->setParameter('situacao', AgendamentoInterface::SITUACAO_AGENDADO)
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
                    "[unidade-%s] Updating appointment ID %s, date %s",
                    $unidade->getId(),
                    $agendamento->getId(),
                    $agendamento->getData()->format('Y-m-d'),
                ));

                $agendamento->setSituacao(AgendamentoInterface::SITUACAO_NAO_COMPARECEU);
                $this->em->persist($agendamento);
                $this->em->flush();

                $unidadeConfig = $this->configService->getUnidadeConfig($agendamento->getUnidade());
                if (!$unidadeConfig) {
                    continue;
                }
                $client = $this->getClient($agendamento->getUnidade(), $unidadeConfig);

                try {
                    $client->updateAgendamento(
                        $agendamento->getOid(),
                        AgendamentoInterface::SITUACAO_NAO_COMPARECEU
                    );
                } catch (Throwable $ex) {
                    $io->error(sprintf(
                        "[unidade-%s] Failed to update remove appointment (OID: %s): %s",
                        $unidade->getId(),
                        $agendamento->getOid(),
                        $ex->getMessage()
                    ));
                }
            }

            $offset += count($agendamentos);
        } while (!empty($agendamentos));

        $io->text(sprintf(
            '[unidade-%s] Total appointments found: %s',
            $unidade->getId(),
            $offset,
        ));
    }
}
