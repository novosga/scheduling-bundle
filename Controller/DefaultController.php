<?php

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
use Novosga\Entity\Agendamento as Entity;
use Novosga\Entity\Agendamento;
use Novosga\SchedulingBundle\Form\AgendamentoType as EntityType;
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
class DefaultController extends AbstractController
{
    const DOMAIN = 'NovosgaSchedulingBundle';

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_scheduling_index", methods={"GET"})
     */
    public function index(Request $request)
    {
        $search = $request->get('q');
        $situacao = $request->get('situacao') ?? Agendamento::SITUACAO_AGENDADO;
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $qb = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e', 'c')
            ->from(Entity::class, 'e')
            ->join('e.cliente', 'c')
            ->where('e.unidade = :unidade')
            ->andWhere('e.situacao = :situacao')
            ->setParameter('unidade', $unidade)
            ->setParameter('situacao', $situacao)
            ->orderBy('e.data', 'DESC');
        
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
        
        $adapter = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $view = new \Pagerfanta\View\TwitterBootstrap4View();
        
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
    
    /**
     * @Route("/new", name="novosga_scheduling_new", methods={"GET", "POST"})
     */
    public function add(Request $request, TranslatorInterface $translator)
    {
        return $this->form($request, $translator, new Entity);
    }
    
    /**
     * @Route("/{id}", name="novosga_scheduling_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, TranslatorInterface $translator, Entity $entity)
    {
        return $this->form($request, $translator, $entity);
    }
    
    private function form(Request $request, TranslatorInterface $translator, Entity $entity)
    {
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        if ($entity->getUnidade() && $entity->getUnidade()->getId() !== $unidade->getId()) {
            return $this->redirectToRoute('novosga_scheduling_index');
        }

        // desabilita edição do agendamento caso já tenha sido confirmado ou veio de integração externa
        $isDisabled = !!$entity->getDataConfirmacao() || !!$entity->getOid();

        $form = $this
            ->createForm(EntityType::class, $entity, [
                'disabled' => $isDisabled,
            ])
            ->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entity->setUnidade($unidade);

                $em->persist($entity);
                $em->flush();
                
                $this->addFlash('success', $translator->trans('label.add_success', [], self::DOMAIN));
                
                return $this->redirectToRoute('novosga_scheduling_edit', [ 'id' => $entity->getId() ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        
        return $this->render('@NovosgaScheduling/default/form.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }
}
