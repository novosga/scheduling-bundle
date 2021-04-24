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

use Novosga\Entity\Servico;
use Novosga\SchedulingBundle\Dto\ServicoConfig;
use Novosga\SchedulingBundle\Dto\ServicoRemoto;
use Novosga\SchedulingBundle\Service\ApiClient;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class ServicoConfigType extends AbstractType
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
            ->add('servicoLocal', EntityType::class, [
                'class' => Servico::class,
                'constraints' => [
                    new NotNull(),
                ],
                'placeholder' => '',
                'label' => 'label.local_service',
            ])
            ->add('servicoRemoto', ChoiceType::class, [
                'placeholder' => '',
                'choices' => $this->client->getServicos(),
                'choice_value' => function ($servico) {
                    if ($servico instanceof ServicoRemoto) {
                        return $servico->getId();
                    }
                    return $servico;
                },
                'choice_label' => function ($servico) {
                    if ($servico instanceof ServicoRemoto) {
                        return "{$servico->getId()} - {$servico->getNome()}";
                    }
                    return $servico;
                },
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.remote_service',
            ]);
    }
    
    /**
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ServicoConfig::class,
            'translation_domain' => 'NovosgaSchedulingBundle',
        ]);
    }
}
