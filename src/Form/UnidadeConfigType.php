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

use Novosga\SchedulingBundle\Service\ExternalApiClientFactory;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * UnidadeConfigType
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class UnidadeConfigType extends AbstractType
{
    public function __construct(
        private readonly ExternalApiClientFactory $clientFactory,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('apiUrl', UrlType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.url_api',
            ])
            ->add('accessToken', TextType::class, [
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.api_access_token',
            ])
            ->add('provider', ChoiceType::class, [
                'placeholder' => '',
                'choices' => [
                    'Mangati Agenda' => 'mangati.agenda',
                ],
                'choice_attr' => [
                    'Mangati Agenda' => ['data-default-url' => 'https://agenda.mangati.com/api/'],
                ],
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.api_provider',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var UnidadeConfig */
            $data = $event->getData();
            $provider = $data->provider;
            $apiUrl = $data->apiUrl;
            $accessToken = $data->accessToken;

            $this->addUnidadeRemota($form, $provider, $apiUrl, $accessToken);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            $provider = $data['provider'] ?? '';
            $apiUrl = $data['apiUrl'] ?? '';
            $accessToken = $data['accessToken'] ?? '';

            $this->addUnidadeRemota($form, $provider, $apiUrl, $accessToken);
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnidadeConfig::class,
            'translation_domain' => 'NovosgaSchedulingBundle',
        ]);
    }

    private function addUnidadeRemota(
        FormInterface $form,
        ?string $provider,
        ?string $apiUrl,
        ?string $accessToken,
    ): void {
        $unidades = [];
        if ($provider && $apiUrl && $accessToken) {
            $client = $this->clientFactory->createFromArgs(
                $provider,
                $apiUrl,
                $accessToken,
            );
            foreach ($client->getUnidades() as $unidade) {
                $unidades[$unidade->nome] = $unidade->id;
            }
        }

        $form
            ->add('unidadeRemota', ChoiceType::class, [
                'placeholder' => '',
                'choices' => $unidades,
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'label.remote_unity',
            ]);
    }
}
