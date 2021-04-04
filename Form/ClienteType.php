<?php

namespace Novosga\SchedulingBundle\Form;

use Novosga\Entity\Cliente;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ClienteType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nome', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'min' => 3, 'max' => 20 ]),
                ],
                'label' => 'form.customer.name',
                'translation_domain' => 'NovosgaSchedulingBundle',
            ])
            ->add('documento', TextType::class, [
                'constraints' => [
                    new NotNull(),
                    new Length([ 'max' => 30 ]),
                ],
                'label' => 'form.customer.id',
                'translation_domain' => 'NovosgaSchedulingBundle',
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [
                    new Email(),
                    new Length([ 'max' => 80 ]),
                ],
                'label' => 'form.customer.email',
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
                'data_class' => Cliente::class
            ]);
    }
}
