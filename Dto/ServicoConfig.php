<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle\Dto;

use JsonSerializable;
use Novosga\Entity\Servico;

class ServicoConfig implements JsonSerializable
{
    /**
     * @var Servico
     */
    private $servicoLocal;

    /**
     * @var int
     */
    private $servicoRemoto;

    public function getServicoRemoto(): ?int
    {
        return $this->servicoRemoto;
    }

    public function setServicoRemoto($servicoRemoto): self
    {
        if ($servicoRemoto instanceof ServicoRemoto) {
            $this->servicoRemoto = $servicoRemoto->getId();
        } else {
            $this->servicoRemoto = (int) $servicoRemoto;
        }

        return $this;
    }

    public function getServicoLocal(): ?Servico
    {
        return $this->servicoLocal;
    }

    public function setServicoLocal(?Servico $servicoLocal): self
    {
        $this->servicoLocal = $servicoLocal;

        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'servicoLocal' => $this->servicoLocal ? $this->servicoLocal->getId() : null,
            'servicoRemoto' => $this->servicoRemoto,
        ];
    }
}