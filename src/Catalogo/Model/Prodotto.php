<?php


namespace Catalogo\Model;

use File\Model\File;

class Prodotto
{
    public $id;
    public $id_marca;
    public $codice;
    public $nome;
    public $nome_it;
    public $nome_en;
    public $nome_de;
    public $nome_fr;
    public $nome_es;
    public $desc_breve;
    public $desc_breve_it;
    public $desc_breve_en;
    public $desc_breve_de;
    public $desc_breve_fr;
    public $desc_breve_es;
    public $desc;
    public $desc_it;
    public $desc_en;
    public $desc_de;
    public $desc_fr;
    public $desc_es;
    public $visibile;
    public $homepage;
    public $categorie;
    public $data_ins;
    public $stato;
    public $info1;
    public $info2;
    public $info3;
    public $info4;
    /**
     * @var File
     */
    public $cover;
    public $images = [];
    /**
     * @var File
     */
    public $pdf;
    public $tags = [];
    public $prezzo;
    public $prezzo_scontato;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setNome($nome)
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


    public function setDesc($desc)
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


    public function setDescBreve($desc_breve)
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

    public function setCodice($codice)
    {
        $this->codice = $codice;
    }

    public function setMarca(Marca $marca)
    {
        $this->marca = $marca;
    }

    public function setIdMarca($id_marca): void
    {
        $this->id_marca = $id_marca;
    }

    public function setCover(File $img)
    {
        $this->cover = $img;
    }

    public function setPdf(File $pdf)
    {
        $this->pdf = $pdf;
    }

    public function setImages($images)
    {
        $this->images = $images;
    }

    public function setTags(array $tags)
    {
        $this->tags = $tags;
    }

    public function setPrezzo($prezzo)
    {
        $this->prezzo = $prezzo;
    }

    /**
     * @param mixed $prezzo_scontato
     */
    public function setPrezzoScontato($prezzo_scontato): void
    {
        $this->prezzo_scontato = $prezzo_scontato;
    }

    /**
     * @param array $categorie
     */
    public function setCategorie(array $categorie): void
    {
        $this->categorie = $categorie;
    }

    /**
     * @param mixed $visibile
     */
    public function setVisibile($visibile): void
    {
        $this->visibile = $visibile;
    }

    /**
     * @param mixed $homepage
     */
    public function setHomepage($homepage): void
    {
        $this->homepage = $homepage;
    }

    /**
     * @param mixed $data_ins
     */
    public function setDataIns($data_ins): void
    {
        $this->data_ins = $data_ins;
    }

    /**
     * @param mixed $info1
     */
    public function setInfo1($info1): void
    {
        $this->info1 = $info1;
    }

    /**
     * @param mixed $info2
     */
    public function setInfo2($info2): void
    {
        $this->info2 = $info2;
    }

    /**
     * @param mixed $info3
     */
    public function setInfo3($info3): void
    {
        $this->info3 = $info3;
    }

    /**
     * @param mixed $info4
     */
    public function setInfo4($info4): void
    {
        $this->info4 = $info4;
    }

    /**
     * @param mixed $stato
     */
    public function setStato($stato): void
    {
        $this->stato = $stato;
    }




}