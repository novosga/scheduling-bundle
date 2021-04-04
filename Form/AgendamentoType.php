<?php

namespace Novosga\SchedulingBundle\Form;

use Novosga\Entity\Agendamento;
use Novosga\Entity\Cliente;
use Novosga\Entity\Servico;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;

class AgendamentoType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cliente', ClienteType::class, [
                'class' => Cliente::class,
                'constraints' => [
                    new Valid(),
                ],
                'label' => 'form.scheduling.customer',
                'translation_domain' => 'NovosgaSchedulingBundle',
            ])
            ->add('servico', EntityType::class, [
                'class' => Servico::class,
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'form.scheduling.service',
                'translation_domain' => 'NovosgaSchedulingBundle',
            ])
            ->add('data', DateType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'form.scheduling.date',
                'translation_domain' => 'NovosgaSchedulingBundle',
            ])
            ->add('hora', TimeType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'form.scheduling.date',
                'translation_domain' => 'NovosgaSchedulingBundle',
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
                'data_class' => Agendamento::class
            ]);
    }
}
