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

namespace Novosga\SchedulingBundle\Service;

use Novosga\Entity\ServicoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Repository\UnidadeMetadataRepositoryInterface;
use Novosga\SchedulingBundle\Mapper\ServicoConfigMapper;
use Novosga\SchedulingBundle\Mapper\UnidadeConfigMapper;
use Novosga\SchedulingBundle\ValueObject\ServicoConfig;
use Novosga\SchedulingBundle\ValueObject\UnidadeConfig;

/**
 * ConfigService
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ConfigService
{
    private const METADATA_NAMESPACE = 'novosga.scheduling';
    private const METADATA_CONFIG_NAMESPACE =  self::METADATA_NAMESPACE . '.config';
    private const METADATA_CONFIG_NAME_PREFIX = 'config_';
    private const METADATA_UNIDADE_NAME = 'unidade';

    public function __construct(
        private readonly UnidadeMetadataRepositoryInterface $unidateMetadataRepository,
        private readonly UnidadeConfigMapper $unidadeConfigMapper,
        private readonly ServicoConfigMapper $servicoConfigMapper,
    ) {
    }

    public function getUnidadeConfig(UnidadeInterface $unidade): ?UnidadeConfig
    {
        $metadata = $this
            ->unidateMetadataRepository
            ->get($unidade, self::METADATA_NAMESPACE, self::METADATA_UNIDADE_NAME);

        if (!$metadata) {
            return null;
        }

        $value = $metadata->getValue();
        return $this->unidadeConfigMapper->toUnidadeConfig($value);
    }

    public function setUnidadeConfig(UnidadeInterface $unidade, UnidadeConfig $config): void
    {
        $this->unidateMetadataRepository->set(
            $unidade,
            self::METADATA_NAMESPACE,
            self::METADATA_UNIDADE_NAME,
            $config
        );
    }

    /** @return ServicoConfig[] */
    public function getServicoConfigs(UnidadeInterface $unidade): array
    {
        $configs = [];
        $configMetadata = $this
            ->unidateMetadataRepository
            ->findByNamespace($unidade, self::METADATA_CONFIG_NAMESPACE);

        foreach ($configMetadata as $meta) {
            $configs[] = $this->servicoConfigMapper->toServicoConfig($meta->getValue());
        }

        return $configs;
    }

    public function getServicoConfig(UnidadeInterface $unidade, ServicoInterface $servico): ?ServicoConfig
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $servico);
        $metadata = $this->unidateMetadataRepository->get($unidade, self::METADATA_CONFIG_NAMESPACE, $name);

        if (!$metadata) {
            return null;
        }

        $value = $metadata->getValue();
        return $this->servicoConfigMapper->toServicoConfig($value);
    }

    public function removeServicoConfig(UnidadeInterface $unidade, ServicoInterface $servico): void
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $servico);
        $this->unidateMetadataRepository->remove($unidade, self::METADATA_CONFIG_NAMESPACE, $name);
    }

    public function setServicoConfig(UnidadeInterface $unidade, ServicoConfig $config): void
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $config->servicoLocal);
        $this->unidateMetadataRepository->set($unidade, self::METADATA_CONFIG_NAMESPACE, $name, $config);
    }

    public function buildServicoConfigMetadataName(
        UnidadeInterface $unidade,
        ServicoInterface $servico
    ): string {
        return self::METADATA_CONFIG_NAME_PREFIX . "{$unidade->getId()}_{$servico->getId()}";
    }
}
