<?php

namespace Novosga\SchedulingBundle\Dto;

class ServicoRemoto
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $nome;

    public function __construct($id = null, $nome = null)
    {
        $this->id = $id;
        $this->nome = $nome;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(?int $nome): self
    {
        $this->nome = $nome;

        return $this;
    }
}