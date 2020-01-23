<?php


namespace Offerte\Model;


use File\Model\File;

class Offerta
{
    public $id;
    public $visibile;
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
    public $desc_breve;
    public $desc_breve_it;
    public $desc_breve_en;
    public $desc_breve_de;
    public $desc_breve_fr;
    public $desc_breve_es;
    public $data_fine;
    public $data_ins;
    public $stato;
    public $img;
    public $images;
    public $categorie;
    /**
     * @var File
     */
    public $cover;
    public $files;

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param mixed $visibile
     */
    public function setVisibile($visibile): void
    {
        $this->visibile = $visibile;
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
     * @param mixed $desc_breve
     */
    public function setDescBreve($desc_breve): void
    {
        $this->desc_breve = $desc_breve;
    }

    /**
     * @param mixed $desc_breve_it
     */
    public function setDescBreveIt($desc_breve_it): void
    {
        $this->desc_breve_it = $desc_breve_it;
    }

    /**
     * @param mixed $desc_breve_en
     */
    public function setDescBreveEn($desc_breve_en): void
    {
        $this->desc_breve_en = $desc_breve_en;
    }

    /**
     * @param mixed $desc_breve_de
     */
    public function setDescBreveDe($desc_breve_de): void
    {
        $this->desc_breve_de = $desc_breve_de;
    }

    /**
     * @param mixed $desc_breve_fr
     */
    public function setDescBreveFr($desc_breve_fr): void
    {
        $this->desc_breve_fr = $desc_breve_fr;
    }

    /**
     * @param mixed $desc_breve_es
     */
    public function setDescBreveEs($desc_breve_es): void
    {
        $this->desc_breve_es = $desc_breve_es;
    }

    /**
     * @param mixed $data_fine
     */
    public function setDataFine($data_fine): void
    {
        $this->data_fine = $data_fine;
    }

    /**
     * @param mixed $data_ins
     */
    public function setDataIns($data_ins): void
    {
        $this->data_ins = $data_ins;
    }

    /**
     * @param mixed $stato
     */
    public function setStato($stato): void
    {
        $this->stato = $stato;
    }

    /**
     * @param mixed $img
     */
    public function setImg($img): void
    {
        $this->img = $img;
    }

    /**
     * @param mixed $images
     */
    public function setImages($images): void
    {
        $this->images = $images;
    }

    /**
     * @param File $cover
     */
    public function setCover(File $cover): void
    {
        $this->cover = $cover;
    }

    /**
     * @param mixed $files
     */
    public function setFiles($files): void
    {
        $this->files = $files;
    }

    /**
     * @param mixed $categorie
     */
    public function setCategorie($categorie): void
    {
        $this->categorie = $categorie;
    }
}