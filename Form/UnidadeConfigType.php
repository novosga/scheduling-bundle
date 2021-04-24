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
use Novosga\SchedulingBundle\Dto\UnidadeRemota;
use Novosga\SchedulingBundle\Service\ApiClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class UnidadeConfigType extends AbstractType
{
    /**
     * @var ApiClient
     */
    private $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('unidadeRemota', ChoiceType::class, [
                'placeholder' => '',
                'choices' => $this->client->getUnidades(),
                'choice_value' => function ($unidade) {
                    if ($unidade instanceof UnidadeRemota) {
                        return $unidade->getId();
                    }
                    return $unidade;
                },
                'choice_label' => function ($unidade) {
                    if ($unidade instanceof UnidadeRemota) {
                        return "{$unidade->getId()} - {$unidade->getNome()}";
                    }
                    return $unidade;
                },
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.remote_unity',
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
