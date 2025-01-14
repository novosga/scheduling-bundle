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

namespace Novosga\SchedulingBundle\Form;

use Novosga\Entity\ServicoInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\Repository\ServicoRepositoryInterface;
use Novosga\SchedulingBundle\Service\ConfigService;
use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Throwable;

/**
 * ServicoConfigType
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ServicoConfigType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly ConfigService $service,
        private readonly ServicoRepositoryInterface $servicoRepository,
        private readonly ExternalApiClientFactory $clientFactory
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var UsuarioInterface $usuario */
        $usuario = $this->security->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $unidadeConfig = $this->service->getUnidadeConfig($unidade);

        $servicos = [];
        try {
            $client = $this->clientFactory->create($unidadeConfig);
            foreach ($client->getServicos() as $servico) {
                $servicos[$servico->nome] = $servico->id;
            }
        } catch (Throwable $ex) {
            $servicos = [];
        }

        $builder
            ->add('servicoLocal', ChoiceType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'placeholder' => '',
                'choices' => $this->servicoRepository->getServicosAtivosUnidade($unidade),
                'choice_value' => fn (?ServicoInterface $servico) => $servico?->getId(),
                'choice_label' => fn (?ServicoInterface $servico) => $servico?->getNome(),
                'label' => 'label.local_service',
            ])
            ->add('servicoRemoto', ChoiceType::class, [
                'placeholder' => '',
                'choices' => $servicos,
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.remote_service',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServicoConfig::class,
            'translation_domain' => 'NovosgaSchedulingBundle',
        ]);
    }
}
