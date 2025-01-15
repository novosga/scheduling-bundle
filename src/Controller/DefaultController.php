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

use Exception;
use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Repository\AgendamentoRepositoryInterface;
use Novosga\SchedulingBundle\Form\AgendamentoType;
use Novosga\SchedulingBundle\NovosgaSchedulingBundle;
use Novosga\Service\AgendamentoServiceInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrap5View;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Scheduling controller.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/", name: "novosga_scheduling_")]
class DefaultController extends AbstractController
{
    #[Route("/", name: "index", methods: ['GET'])]
    public function index(
        Request $request,
        AgendamentoRepositoryInterface $repository,
    ): Response {
        $search = $request->get('q', '');
        $situacao = $request->get('situacao', '');
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $qb = $repository
            ->createQueryBuilder('e')
            ->select('e', 'c')
            ->join('e.cliente', 'c')
            ->where('e.unidade = :unidade')
            ->setParameter('unidade', $unidade)
            ->orderBy('e.data', 'DESC');

        if ($situacao) {
            $qb
                ->andWhere('e.situacao = :situacao')
                ->setParameter('situacao', $situacao);
        }

        if (!empty($search)) {
            $where = [
                'c.email LIKE :s',
                'c.documento LIKE :s'
            ];
            $qb->setParameter('s', "%{$search}%");

            $tokens = explode(' ', $search);

            for ($i = 0; $i < count($tokens); $i++) {
                $value = $tokens[$i];
                $v1 = "n{$i}";
                $where[] = "(UPPER(c.nome) LIKE UPPER(:{$v1}))";
                $qb->setParameter($v1, "{$value}%");
            }

            $qb->andWhere(join(' OR ', $where));
        }

        $query = $qb->getQuery();

        $currentPage = max(1, (int) $request->get('p'));

        $adapter = new QueryAdapter($query);
        $view = new TwitterBootstrap5View();
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setCurrentPage($currentPage);

        $path = $this->generateUrl('novosga_scheduling_index');
        $html = $view->render(
            $pagerfanta,
            function ($page) use ($request, $path) {
                $params = [];
                $vars = ['q', 'situacao'];
                foreach ($vars as $name) {
                    $value = $request->get($name);
                    if ($value !== null) {
                        $params[] = "{$name}={$value}";
                    }
                }
                $path .= "?p={$page}";
                if (!empty($params)) {
                    $path .= '&' . implode('&', $params);
                }
                return $path;
            },
            [
                'proximity' => 3,
                'prev_message' => '←',
                'next_message' => '→',
            ]
        );

        $agendamentos = $pagerfanta->getCurrentPageResults();

        return $this->render('@NovosgaScheduling/default/index.html.twig', [
            'agendamentos' => $agendamentos,
            'paginacao' => $html,
        ]);
    }

    #[Route("/new", name: "new", methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        AgendamentoServiceInterface $service,
        TranslatorInterface $translator,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $entity = $service->build()->setUnidade($unidade);

        return $this->form($request, $service, $translator, $entity);
    }

    #[Route("/{id}/edit", name: "edit", methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        AgendamentoServiceInterface $service,
        TranslatorInterface $translator,
        ?int $id = null,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $entity = $service->getById($id);
        if (!$entity) {
            throw $this->createNotFoundException();
        }

        if ($entity->getUnidade() && $entity->getUnidade()->getId() !== $unidade->getId()) {
            return $this->redirectToRoute('novosga_scheduling_index');
        }

        return $this->form($request, $service, $translator, $entity);
    }

    private function form(
        Request $request,
        AgendamentoServiceInterface $service,
        TranslatorInterface $translator,
        AgendamentoInterface $entity,
    ): Response {
        // desabilita edição do agendamento caso já tenha sido confirmado ou veio de integração externa
        $isDisabled = !!$entity->getDataConfirmacao() || !!$entity->getOid();
        $form = $this
            ->createForm(AgendamentoType::class, $entity, [
                'disabled' => $isDisabled,
            ])
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->save($entity);

                $this->addFlash(
                    'success',
                    $translator->trans(
                        'label.add_success',
                        [],
                        NovosgaSchedulingBundle::getDomain(),
                    )
                );

                return $this->redirectToRoute('novosga_scheduling_edit', [
                    'id' => $entity->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@NovosgaScheduling/default/form.html.twig', [
            'entity' => $entity,
            'form' => $form,
        ]);
    }
}
