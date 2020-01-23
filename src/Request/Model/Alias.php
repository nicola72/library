<?php


namespace Request\Model;


class Alias
{
    public $id;
    public $dominio;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setDominio($dominio)
    {
        $this->dominio = $dominio;
    }
}