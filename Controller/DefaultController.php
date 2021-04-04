<?php

namespace Novosga\SchedulingBundle\Controller;

use Exception;
use Novosga\Entity\Agendamento as Entity;
use Novosga\Entity\Cliente;
use Novosga\Http\Envelope;
use Novosga\SchedulingBundle\Form\AgendamentoType as EntityType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

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
        
        $qb = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e', 'c')
            ->from(Entity::class, 'e')
            ->join('e.cliente', 'c');
        
        $params = [];
        
        if (!empty($search)) {
            $where       = [ 
                'c.email LIKE :s',
                'c.documento LIKE :s'
            ];
            $params['s'] = "%{$search}%";
            
            $tokens = explode(' ', $search);
            
            for ($i = 0; $i < count($tokens); $i++) {
                $value       = $tokens[$i];
                $v1          = "n{$i}";
                $where[]     = "(UPPER(c.nome) LIKE UPPER(:{$v1}))";
                $params[$v1] = "{$value}%";
            }
            
            $qb->andWhere(join(' OR ', $where));
        }
        
        $query = $qb
            ->setParameters($params)
            ->getQuery();
        
        $currentPage = max(1, (int) $request->get('p'));
        
        $adapter    = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $view       = new \Pagerfanta\View\TwitterBootstrap4View();
        
        $pagerfanta->setCurrentPage($currentPage);
        
        $path = $this->generateUrl('novosga_scheduling_index');
        $html = $view->render(
            $pagerfanta,
            function ($page) use ($request, $path) {
                $q = $request->get('q');
                return "{$path}?q={$q}&p={$page}";
            },
            [
                'proximity' => 3,
                'prev_message' => '←',
                'next_message' => '→',
            ]
        );
        
        $clientes = $pagerfanta->getCurrentPageResults();
        
        return $this->render('@NovosgaScheduling/default/index.html.twig', [
            'agendamentos' => $agendamentos,
            'paginacao'    => $html,
        ]);
    }
    
    /**
     *
     * @param Request $request
     * @param int $id
     * @return Response
     *
     * @Route("/new", name="novosga_scheduling_new", methods={"GET", "POST"})
     * @Route("/{id}/edit", name="novosga_scheduling_edit", methods={"GET", "POST"})
     */
    public function form(Request $request, TranslatorInterface $translator, Entity $entity = null)
    {
        if (!$entity) {
            $entity = new Entity();
        }
        
        $em   = $this->getDoctrine()->getManager();
        $form = $this
            ->createForm(EntityType::class, $entity, [])
            ->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $isNew = !$entity->getId();

                if ($isNew) {
                    $em->persist($entity);
                } else {
                    $em->merge($entity);
                }
                
                $em->flush();
                
                $this->addFlash('success', $translator->trans('label.add_sucess', [], self::DOMAIN));
                
                return $this->redirectToRoute('novosga_scheduling_edit', [ 'id' => $entity->getId() ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        
        return $this->render('@NovosgaScheduling/default/form.html.twig', [
            'entity' => $entity,
            'form'   => $form->createView(),
        ]);
    }
}
