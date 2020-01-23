<?php
namespace FotoGallery\Model;

use Categorie\Model\Categoria;

class FotoGallery
{
    public $id;
    /**
     * @var array Categoria
     */
    public $categorie = [];
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
    public $visibile;
    public $data_ins;
    public $last_mod;
    public $cover;
    public $images;

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param array $categorie
     */
    public function setCategorie(array $categorie): void
    {
        $this->categorie = $categorie;
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
     * @param mixed $visibile
     */
    public function setVisibile($visibile): void
    {
        $this->visibile = $visibile;
    }

    /**
     * @param mixed $data_ins
     */
    public function setDataIns($data_ins): void
    {
        $this->data_ins = $data_ins;
    }

    /**
     * @param mixed $last_mod
     */
    public function setLastMod($last_mod): void
    {
        $this->last_mod = $last_mod;
    }

    /**
     * @param mixed $cover
     */
    public function setCover($cover): void
    {
        $this->cover = $cover;
    }

    /**
     * @param mixed $images
     */
    public function setImages($images): void
    {
        $this->images = $images;
    }




}