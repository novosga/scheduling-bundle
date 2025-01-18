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

namespace Novosga\SchedulingBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Novosga\Entity\ClienteInterface;
use Novosga\Form\ClienteType;
use Novosga\Repository\ClienteRepositoryInterface;
use Novosga\Service\ClienteServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ClientesController
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/clientes", name: "novosga_scheduling_clientes_")]
class ClientesController extends AbstractController
{
    #[Route("/autocomplete", name: "autocomplete", methods: ["GET"])]
    public function autocomplete(
        Request $request,
        ClienteRepositoryInterface $repository,
    ): Response {
        $search = $request->get('q', '');
        $clientes = $repository
            ->createQueryBuilder('e')
            ->where('UPPER(e.nome) LIKE UPPER(:nome)')
            ->orWhere('e.documento = :documento')
            ->setParameter('nome', "%{$search}%")
            ->setParameter('documento', $search)
            ->orderBy('UPPER(e.nome)', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        $result = array_map(
            fn (ClienteInterface $cliente) => [
                'id' => $cliente->getId(),
                'value' => sprintf('%s - %s', $cliente->getDocumento(), $cliente->getNome()),
            ],
            $clientes,
        );

        return $this->json($result);
    }

    #[Route("/form", name: "form", methods: ["GET", "POST"])]
    public function form(
        Request $request,
        EntityManagerInterface $em,
        ClienteServiceInterface $clienteService,
    ): Response {
        $cliente = $clienteService->build();
        $form = $this
            ->createForm(ClienteType::class, $cliente, [
                'csrf_protection' => false,
            ])
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($cliente);
                $em->flush();

                $this->addFlash('success', 'Cliente salvo com sucesso');
            } catch (Exception $ex) {
                $this->addFlash('danger', $ex->getMessage());
            }
        }

        return $this->render('@NovosgaScheduling/clientes/form.html.twig', [
            'form' => $form,
        ]);
    }
}
