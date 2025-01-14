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
use Novosga\Entity\UsuarioInterface;
use Novosga\SchedulingBundle\Form\ServicoConfigType;
use Novosga\SchedulingBundle\Form\UnidadeConfigType;
use Novosga\SchedulingBundle\NovosgaSchedulingBundle;
use Novosga\SchedulingBundle\Service\ConfigService;
use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Novosga\Service\ServicoServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Scheduling Config controller.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/config", name: "novosga_scheduling_config_")]
class ConfigController extends AbstractController
{
    #[Route("/", name: "index", methods: ["GET", "POST"])]
    public function index(
        Request $request,
        ConfigService $service,
        TranslatorInterface $translator,
        ExternalApiClientFactory $clientFactory,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $unidadeConfig = $service->getUnidadeConfig($unidade);
        $servicosRemotos = [];

        if (!$unidadeConfig) {
            $unidadeConfig = new UnidadeConfig();
        } else {
            try {
                $servicosRemotos = $clientFactory->create($unidadeConfig)->getServicos();
            } catch (Throwable $ex) {
            }
        }

        $form = $this
            ->createForm(UnidadeConfigType::class, $unidadeConfig)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->setUnidadeConfig($unidade, $unidadeConfig);

            $this->addFlash('success', $translator->trans(
                'label.add_config_success',
                [],
                NovosgaSchedulingBundle::getDomain(),
            ));

            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        $servicoConfigs = $service->getServicoConfigs($unidade);

        return $this->render('@NovosgaScheduling/config/index.html.twig', [
            'unidade' => $unidade,
            'servicoConfigs' => $servicoConfigs,
            'servicosRemotos' => $servicosRemotos,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/new", name: "new", methods: ["GET", "POST"])]
    public function add(
        Request $request,
        TranslatorInterface $translator,
        ConfigService $configService,
    ): Response {
        return $this->form($request, $translator, $configService, new ServicoConfig());
    }

    #[Route("/{id}/edit", name: "edit", methods: ["GET", "POST"])]
    public function edit(
        Request $request,
        TranslatorInterface $translator,
        ConfigService $configService,
        ServicoServiceInterface $servicoService,
        int $id,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $servico = $servicoService->getById($id);
        if (!$servico) {
            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        $config = $configService->getServicoConfig($unidade, $servico);
        if (!$config) {
            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        return $this->form($request, $translator, $configService, $config);
    }

    #[Route("/{id}/delete", name: "delete", methods: ["POST"])]
    public function delete(
        ConfigService $configService,
        ServicoServiceInterface $servicoService,
        int $id,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servico = $servicoService->getById($id);
        if (!$servico) {
            return $this->redirectToRoute('novosga_scheduling_config_index');
        }

        $configService->removeServicoConfig($unidade, $servico);

        return $this->redirectToRoute('novosga_scheduling_config_index');
    }

    private function form(
        Request $request,
        TranslatorInterface $translator,
        ConfigService $configService,
        ServicoConfig $config
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $form = $this
            ->createForm(ServicoConfigType::class, $config, [])
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $configService->setServicoConfig($unidade, $config);

                $this->addFlash('success', $translator->trans(
                    'label.add_config_success',
                    [],
                    NovosgaSchedulingBundle::getDomain(),
                ));

                return $this->redirectToRoute('novosga_scheduling_config_edit', [
                    'id' => $config->servicoLocal->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@NovosgaScheduling/config/form.html.twig', [
            'config' => $config,
            'form' => $form,
        ]);
    }
}
