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

class UnidadeConfig implements JsonSerializable
{
    /**
     * @var int
     */
    private $unidadeRemota;

    public function getUnidadeRemota(): ?int
    {
        return $this->unidadeRemota;
    }

    public function setUnidadeRemota($unidadeRemota): self
    {
        if ($unidadeRemota instanceof UnidadeRemota) {
            $this->unidadeRemota = $unidadeRemota->getId();
        } else {
            $this->unidadeRemota = (int) $unidadeRemota;
        }

        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'unidadeRemota' => $this->unidadeRemota,
        ];
    }
}
