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

use Novosga\Entity\UnidadeInterface;
use Novosga\Repository\UnidadeRepositoryInterface;
use Novosga\SchedulingBundle\Service\SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    public function __construct(
        private readonly SyncService $syncService,
        private readonly UnidadeRepositoryInterface $unidadeRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Novo SGA Scheduling Sync');

        /** @var UnidadeInterface[] */
        $unidades = $this->unidadeRepository->findBy(['ativo' => true]);

        foreach ($unidades as $unidade) {
            $io->info(sprintf(
                '[unidade-%s] Syncing appointments for Unit %s',
                $unidade->getId(),
                $unidade->getNome(),
            ));

            $result = $this->syncService->syncUnidade($unidade);

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $io->error($error);
                }
            }

            $io->success(sprintf(
                '[unidade-%s] Done. Total retrieved: %d. Total saved: %d.',
                $unidade->getId(),
                $result['total'],
                $result['saved'],
            ));
        }

        return Command::SUCCESS;
    }
}
