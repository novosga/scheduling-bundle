<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle\Service;

use App\Repository\ORM\ClienteRepository;
use App\Repository\ORM\ServicoRepository;
use App\Repository\ORM\UnidadeMetadataRepository;
use DateTime;
use Novosga\Entity\Agendamento;
use Novosga\Entity\Cliente;
use Novosga\Entity\Servico;
use Novosga\Entity\Unidade;
use Novosga\SchedulingBundle\Dto\ServicoConfig;
use Novosga\SchedulingBundle\Dto\UnidadeConfig;

class SchedulingService
{
    const METADATA_NAMESPACE = 'novosga.scheduling';
    const METADATA_CONFIG_NAMESPACE =  self::METADATA_NAMESPACE . '.config';
    const METADATA_CONFIG_NAME_PREFIX = 'config_';
    const METADATA_UNIDADE_NAME = 'unidade';

    /**
     * @var ServicoRepository
     */
    private $servicoRepository;

    /**
     * @var UnidadeMetadataRepository
     */
    private $unidateMetadataRepository;

    /**
     * @var ClienteRepository
     */
    private $clienteRepository;


    public function __construct(
        ServicoRepository $servicoRepository,
        UnidadeMetadataRepository $unidateMetadataRepository,
        ClienteRepository $clienteRepository
    ) 
    {
        $this->servicoRepository = $servicoRepository;
        $this->unidateMetadataRepository = $unidateMetadataRepository;
        $this->clienteRepository = $clienteRepository;
    }

    public function getUnidadeConfig(Unidade $unidade): ?UnidadeConfig
    {
        $metadata = $this->unidateMetadataRepository->get($unidade, self::METADATA_NAMESPACE, self::METADATA_UNIDADE_NAME);

        if (!$metadata) {
            return null;
        }

        $value = $metadata->getValue();
        return $this->toUnidadeConfig($value);
    }

    public function setUnidadeConfig(Unidade $unidade, UnidadeConfig $config)
    {
        $this->unidateMetadataRepository->set(
            $unidade,
            self::METADATA_NAMESPACE,
            self::METADATA_UNIDADE_NAME,
            $config
        );
    }

    /**
     * @return ServicoConfig[]
     */
    public function getServicoConfigs(Unidade $unidade): array
    {
        $configMetadata = $this
            ->unidateMetadataRepository
            ->findByNamespace($unidade, self::METADATA_CONFIG_NAMESPACE);
        $configs = [];

        foreach ($configMetadata as $meta) {
            $configs[] = $this->toServiceConfig($meta->getValue());
        }

        return $configs;
    }

    public function getServicoConfig(Unidade $unidade, Servico $servico): ?ServicoConfig
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $servico);
        $metadata = $this->unidateMetadataRepository->get($unidade, self::METADATA_CONFIG_NAMESPACE, $name);

        if (!$metadata) {
            return null;
        }

        $value = $metadata->getValue();
        return $this->toServiceConfig($value);
    }

    public function removeServicoConfig(Unidade $unidade, Servico $servico)
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $servico);
        $this->unidateMetadataRepository->remove($unidade, self::METADATA_CONFIG_NAMESPACE, $name);
    }

    public function setServicoConfig(Unidade $unidade, ServicoConfig $config)
    {
        $name = $this->buildServicoConfigMetadataName($unidade, $config->getServicoLocal());
        $this->unidateMetadataRepository->set($unidade, self::METADATA_CONFIG_NAMESPACE, $name, $config);
    }

    public function toUnidadeConfig(array $value): UnidadeConfig
    {
        $config = new UnidadeConfig();
        $config->setUnidadeRemota($value['unidadeRemota']);

        return $config;
    }

    public function toServiceConfig(array $value): ServicoConfig
    {
        $config = new ServicoConfig();
        $config
            ->setServicoLocal($this->servicoRepository->find($value['servicoLocal']))
            ->setServicoRemoto($value['servicoRemoto']);

        return $config;
    }

    public function updateCliente(Cliente $cliente, array $value): Cliente
    {
        return $cliente
            ->setNome($value['nome'] ?? $cliente->getNome())
            ->setDocumento($value['documento'] ?? $cliente->getDocumento())
            ->setEmail($value['email'] ?? $cliente->getEmail())
            ->setTelefone($value['telefone'] ?? $cliente->getTelefone());
    }

    public function toAgendamento(Unidade $unidade, Servico $servico, array $value): Agendamento
    {
        // 'data' => $this->data->format('Y-m-d'),
        $data = DateTime::createFromFormat('Y-m-d', $value['data']);
        $hora = DateTime::createFromFormat('H:i', $value['horaInicio']);

        $oid = $value['id'];
        $documento = $value['documento'] ?? '';

        $cliente = null;
        $clientes = $this->clienteRepository->findByDocumento($documento);
        if (count($clientes)) {
            $cliente = $clientes[0];
        }
        if (!$cliente) {
            $cliente = new Cliente();
        }

        $cliente = $this->updateCliente($cliente, $value);

        $agendamento = new Agendamento();
        $agendamento
            ->setOid($oid)
            ->setServico($servico)
            ->setUnidade($unidade)
            ->setData($data)
            ->setHora($hora)
            ->setCliente($cliente);

        return $agendamento;
    }

    public function buildServicoConfigMetadataName(Unidade $unidade, Servico $servico): string
    {
        return self::METADATA_CONFIG_NAME_PREFIX . "{$unidade->getId()}_{$servico->getId()}";
    }
}
