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
use Novosga\Entity\Servico;
use Novosga\SchedulingBundle\Dto\ServicoConfig;
use Novosga\SchedulingBundle\Dto\UnidadeConfig;
use Novosga\SchedulingBundle\Form\ServicoConfigType;
use Novosga\SchedulingBundle\Form\UnidadeConfigType;
use Novosga\SchedulingBundle\Service\SchedulingService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Scheduling Config controller.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 * 
 * @Route("/config")
 */
class ConfigController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_scheduling_config_index", methods={"GET", "POST"})
     */
    public function index(Request $request, SchedulingService $service, TranslatorInterface $translator)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $unidadeConfig = $service->getUnidadeConfig($unidade);

        if (!$unidadeConfig) {
            $unidadeConfig = new UnidadeConfig();
        }
        
        $form = $this
            ->createForm(UnidadeConfigType::class, $unidadeConfig)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->setUnidadeConfig($unidade, $unidadeConfig);
                
            $this->addFlash('success', $translator->trans('label.add_config_success', [], DefaultController::DOMAIN));
            
            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        $servicoConfigs = $service->getServicoConfigs($unidade);
        
        return $this->render('@NovosgaScheduling/config/index.html.twig', [
            'unidade' => $unidade,
            'servicoConfigs' => $servicoConfigs,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * @Route("/new", name="novosga_scheduling_config_new", methods={"GET", "POST"})
     */
    public function add(Request $request, TranslatorInterface $translator, SchedulingService $service)
    {
        return $this->form($request, $translator, $service, new ServicoConfig());
    }
    
    /**
     * @Route("/{id}", name="novosga_scheduling_config_edit", methods={"GET", "POST"})
     */
    public function edit(
        Request $request,
        TranslatorInterface $translator,
        SchedulingService $service,
        Servico $servico
    ) {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $config = $service->getServicoConfig($unidade, $servico);
        
        if (!$config) {
            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        return $this->form($request, $translator, $service, $config);
    }
    
    private function form(
        Request $request,
        TranslatorInterface $translator,
        SchedulingService $service,
        ServicoConfig $config
    ) {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $form = $this
            ->createForm(ServicoConfigType::class, $config, [])
            ->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->setServicoConfig($unidade, $config);
                
                $this->addFlash('success', $translator->trans('label.add_config_success', [], DefaultController::DOMAIN));
                
                return $this->redirectToRoute('novosga_scheduling_config_edit', [ 'id' => $config->getServicoLocal()->getId() ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        
        return $this->render('@NovosgaScheduling/config/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
