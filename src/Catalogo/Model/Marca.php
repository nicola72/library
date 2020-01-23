<?php

namespace Catalogo\Model;


class Marca
{
    public $id;
    public $nome;
    public $logo;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setNome($nome)
    {
        $this->nome = $nome;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
    }
}