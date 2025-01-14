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

use Novosga\Entity\AgendamentoInterface;
use Novosga\Entity\ClienteInterface;
use Novosga\Entity\ServicoInterface;
use Novosga\Repository\ClienteRepositoryInterface;
use Novosga\Repository\ServicoRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * AgendamentoType
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class AgendamentoType extends AbstractType
{
    public function __construct(
        private readonly ServicoRepositoryInterface $servicoRepository,
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('servico', ChoiceType::class, [
                'choices' => $this->servicoRepository->findAll(),
                'choice_label' => fn (?ServicoInterface $servico) => $servico?->getNome(),
                'placeholder' => '',
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'form.scheduling.service',
            ])
            ->add('data', DateType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'form.scheduling.date',
            ])
            ->add('hora', TimeType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'widget' => 'single_text',
                'html5' => true,
                'label' => 'form.scheduling.time',
            ]);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $form = $event->getForm();
                /** @var AgendamentoInterface */
                $data = $event->getData();
                $this->addCliente($form, $data->getCliente()?->getId() ?? 0);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $form = $event->getForm();
                $data = $event->getData();
                $id = $data['cliente'] ?? 0;
                $this->addCliente($form, $id);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => AgendamentoInterface::class,
                'translation_domain' => 'NovosgaSchedulingBundle',
            ]);
    }

    private function addCliente(FormInterface $form, int|string $id): void
    {
        $form
            ->add('cliente', ChoiceType::class, [
                'placeholder' => '',
                'constraints' => [
                    new NotNull(),
                ],
                'choices' => $this->clienteRepository
                        ->createQueryBuilder('e')
                        ->where('e.id = :id')
                        ->setParameter('id', $id)
                        ->getQuery()
                        ->getResult(),
                'choice_value' => fn (?ClienteInterface $cliente) => $cliente?->getId(),
                'choice_label' => fn (?ClienteInterface $cliente) =>
                        sprintf('%s - %s', $cliente?->getDocumento(), $cliente?->getNome()),
                'label' => 'form.scheduling.customer',
            ]);
    }
}
