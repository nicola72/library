<?php


namespace Catalogo\Model;


class TipoTag
{
    public $id;
    public $nome;
    public $nome_it;
    public $nome_en;
    public $nome_de;
    public $nome_fr;
    public $nome_es;

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param mixed $nome
     */
    public function setNome($nome): void
    {
        $this->nome = $nome;
    }

    /**
     * @param mixed $nome_it
     */
    public function setNomeIt($nome_it): void
    {
        $this->nome_it = $nome_it;
    }

    /**
     * @param mixed $nome_en
     */
    public function setNomeEn($nome_en): void
    {
        $this->nome_en = $nome_en;
    }

    /**
     * @param mixed $nome_de
     */
    public function setNomeDe($nome_de): void
    {
        $this->nome_de = $nome_de;
    }

    /**
     * @param mixed $nome_fr
     */
    public function setNomeFr($nome_fr): void
    {
        $this->nome_fr = $nome_fr;
    }

    /**
     * @param mixed $nome_es
     */
    public function setNomeEs($nome_es): void
    {
        $this->nome_es = $nome_es;
    }


}