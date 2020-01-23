<?php
namespace Catalogo\Model;

class TipoAttributo
{
    public $id;
    public $nome;
    public $label;
    public $label_it;
    public $label_en;
    public $label_de;
    public $label_fr;
    public $label_es;
    public $desc;
    public $desc_it;
    public $desc_en;
    public $desc_de;
    public $desc_fr;
    public $desc_es;
    public $visibile;

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
     * @param mixed $label
     */
    public function setLabel($label): void
    {
        $this->label = $label;
    }

    /**
     * @param mixed $label_it
     */
    public function setLabelIt($label_it): void
    {
        $this->label_it = $label_it;
    }

    /**
     * @param mixed $label_en
     */
    public function setLabelEn($label_en): void
    {
        $this->label_en = $label_en;
    }

    /**
     * @param mixed $label_de
     */
    public function setLabelDe($label_de): void
    {
        $this->label_de = $label_de;
    }

    /**
     * @param mixed $label_fr
     */
    public function setLabelFr($label_fr): void
    {
        $this->label_fr = $label_fr;
    }

    /**
     * @param mixed $label_es
     */
    public function setLabelEs($label_es): void
    {
        $this->label_es = $label_es;
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


}