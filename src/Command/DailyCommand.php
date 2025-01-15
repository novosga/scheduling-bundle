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

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Repository\AgendamentoRepositoryInterface;
use Novosga\SchedulingBundle\Service\ConfigService;
use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
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
        private readonly AgendamentoRepositoryInterface $agendamentoRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Novo SGA Scheduling Daily');

        $today = new DateTime();
        $today->setTime(0, 0, 0, 0);
        $limit = 100;
        $offset = 0;

        $query = $this
            ->agendamentoRepository
            ->createQueryBuilder('e')
            ->where('e.situacao = :situacao')
            ->andWhere('e.data < :today')
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
                    "Updating schedule ID %s, date %s",
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
                        "Failed to update remove schedule (OID: %s): %s",
                        $agendamento->getOid(),
                        $ex->getMessage()
                    ));
                }
            }

            $offset += count($agendamentos);
        } while (!empty($agendamentos));

        return Command::SUCCESS;
    }
}
