<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle\Form;

use Novosga\SchedulingBundle\Dto\UnidadeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class UnidadeConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('unidadeRemota', IntegerType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.remote_unity',
            ])
            ->add('url', UrlType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.url_api',
            ]);
    }
    
    /**
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnidadeConfig::class,
            'translation_domain' => 'NovosgaSchedulingBundle',
        ]);
    }
}
