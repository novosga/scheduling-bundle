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
use Novosga\SchedulingBundle\Service\SyncService;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Novosga\Service\ServicoServiceInterface;
use Symfony\Component\Routing\Attribute\Route;
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
            return $this->redirectToRoute('novosga_scheduling_config_start');
        }

        try {
            $servicosRemotos = $clientFactory->create($unidadeConfig)->getServicos();
        } catch (Throwable $ex) {
            $this->addFlash('danger', $ex->getMessage());
        }

        $servicoConfigs = $service->getServicoConfigs($unidade);

        return $this->render('@NovosgaScheduling/config/index.html.twig', [
            'unidade' => $unidade,
            'unidadeConfig' => $unidadeConfig,
            'servicoConfigs' => $servicoConfigs,
            'servicosRemotos' => $servicosRemotos,
        ]);
    }

    #[Route("/start", name: "start", methods: ["GET", "POST"])]
    public function start(
        Request $request,
        ConfigService $service,
        TranslatorInterface $translator,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $unidadeConfig = $service->getUnidadeConfig($unidade) ?? new UnidadeConfig();

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

        return $this->render('@NovosgaScheduling/config/start.html.twig', [
            'unidade' => $unidade,
            'form' => $form,
        ]);
    }

    #[Route("/new", name: "new", methods: ["GET", "POST"])]
    public function add(
        Request $request,
        TranslatorInterface $translator,
        ConfigService $configService,
    ): Response {
        return $this->form($request, $translator, $configService, new ServicoConfig(), true);
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

        return $this->form($request, $translator, $configService, $config, false);
    }

    #[Route("/sync", name: "sync", methods: ["POST"])]
    public function sync(
        SyncService $syncService,
        TranslatorInterface $translator,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $result = $syncService->syncUnidade($unidade);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->addFlash('danger', $error);
            }
        } else {
            $this->addFlash('success', $translator->trans(
                'label.sync_success',
                ['%total%' => $result['total'], '%saved%' => $result['saved']],
                NovosgaSchedulingBundle::getDomain(),
            ));
        }

        return $this->redirectToRoute('novosga_scheduling_config_index');
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
        ServicoConfig $config,
        bool $isNew,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $form = $this
            ->createForm(ServicoConfigType::class, $config, [
                'isNew' => $isNew,
            ])
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
