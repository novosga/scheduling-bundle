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
use Novosga\Entity\ServicoInterface;
use Novosga\Form\ClienteType;
use Novosga\Repository\ServicoRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;

class AgendamentoType extends AbstractType
{
    public function __construct(
        private readonly ServicoRepositoryInterface $servicoRepository,
    ) {
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cliente', ClienteType::class, [
                'constraints' => [
                    new Valid(),
                ],
                'label' => 'form.scheduling.customer',
            ])
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
    }
    
    /**
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => AgendamentoInterface::class,
                'translation_domain' => 'NovosgaSchedulingBundle',
            ]);
    }
}
