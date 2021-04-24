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
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\Agendamento;
use Novosga\SchedulingBundle\Service\ApiClient;
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
class DailyCommand extends Command
{
    protected static $defaultName = 'novosga:scheduling:daily';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ApiClient
     */
    private $client;

    /**
     * @var AgendamentoRepository
     */
    private $agendamentoRepository;

    public function __construct(
        EntityManagerInterface $em,
        ApiClient $client,
        AgendamentoRepository $agendamentoRepository
    ) 
    {
        parent::__construct();
        $this->em = $em;
        $this->client = $client;
        $this->agendamentoRepository = $agendamentoRepository;
    }

    protected function configure()
    {
        $this
            ->setDescription('Atualiza a situação dos agendamentos como nao_compareceu');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
            ->setParameter('situacao', Agendamento::SITUACAO_AGENDADO)
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

                $agendamento->setSituacao(Agendamento::SITUACAO_NAO_COMPARECEU);
                $this->em->persist($agendamento);
                $this->em->flush();
                
                try {
                    $this->client->updateAgendamento(
                        $agendamento->getOid(),
                        Agendamento::SITUACAO_NAO_COMPARECEU
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

        return 0;
    }
}
