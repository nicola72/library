<?php
namespace Catalogo\Model;

use Catalogo\Model\TipoTag;
use Categorie\Model\Categoria;

class Tag
{
    public $id;
    public $nome;
    public $nome_it;
    public $nome_en;
    public $nome_de;
    public $nome_fr;
    public $nome_es;
    public $desc;
    public $desc_it;
    public $desc_en;
    public $desc_de;
    public $desc_fr;
    public $desc_es;
    public $id_tipo;
    /**
     * @var array Categoria
     */
    public $categorie = [];
    /**
     * @var TipoTag
     */
    public $tipo_tag;

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

    /**
     * @param mixed $desc
     */
    public function setDesc($desc): void
    {
        $this->desc = $desc;
    }

    /**
     * @param mixed $desc_it
     */
    public function setDescIt($desc_it): void
    {
        $this->desc_it = $desc_it;
    }

    /**
     * @param mixed $desc_en
     */
    public function setDescEn($desc_en): void
    {
        $this->desc_en = $desc_en;
    }

    /**
     * @param mixed $desc_de
     */
    public function setDescDe($desc_de): void
    {
        $this->desc_de = $desc_de;
    }

    /**
     * @param mixed $desc_fr
     */
    public function setDescFr($desc_fr): void
    {
        $this->desc_fr = $desc_fr;
    }

    /**
     * @param mixed $desc_es
     */
    public function setDescEs($desc_es): void
    {
        $this->desc_es = $desc_es;
    }

    /**
     * @param mixed $id_tipo
     */
    public function setIdTipo($id_tipo): void
    {
        $this->id_tipo = $id_tipo;
    }

    /**
     * @param TipoTag $tipo_tag
     */
    public function setTipoTag(TipoTag $tipo_tag): void
    {
        $this->tipo_tag = $tipo_tag;
    }

    /**
     * @param array $categorie
     */
    public function setCategorie(array $categorie): void
    {
        $this->categorie = $categorie;
    }


}