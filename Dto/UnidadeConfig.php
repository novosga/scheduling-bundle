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
     * @var string
     */
    private $url;

    /**
     * @var int
     */
    private $unidadeRemota;

    public function getUnidadeRemota(): string
    {
        return $this->unidadeRemota;
    }

    public function setUnidadeRemota(int $unidadeRemota): self
    {
        $this->unidadeRemota = $unidadeRemota;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'unidadeRemota' => $this->unidadeRemota,
            'url' => $this->url,
        ];
    }
}
