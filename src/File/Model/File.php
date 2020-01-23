<?php
namespace File\Model;

class File
{
    public $id;
    public $nome;
    public $id_tipo;
    public $modulo;
    public $id_elem;
    public $titolo;
    public $titolo_it;
    public $titolo_en;
    public $titolo_de;
    public $titolo_fr;
    public $titolo_es;
    public $didascalia;
    public $didascalia_it;
    public $didascalia_en;
    public $didascalia_de;
    public $didascalia_fr;
    public $didascalia_es;
    public $ordine;
    public $data_ins;

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
     * @param mixed $id_tipo
     */
    public function setIdTipo($id_tipo): void
    {
        $this->id_tipo = $id_tipo;
    }

    /**
     * @param mixed $modulo
     */
    public function setModulo($modulo): void
    {
        $this->modulo = $modulo;
    }

    /**
     * @param mixed $id_elem
     */
    public function setIdElem($id_elem): void
    {
        $this->id_elem = $id_elem;
    }

    /**
     * @param mixed $titolo
     */
    public function setTitolo($titolo): void
    {
        $this->titolo = $titolo;
    }

    /**
     * @param mixed $titolo_it
     */
    public function setTitoloIt($titolo_it): void
    {
        $this->titolo_it = $titolo_it;
    }

    /**
     * @param mixed $titolo_en
     */
    public function setTitoloEn($titolo_en): void
    {
        $this->titolo_en = $titolo_en;
    }

    /**
     * @param mixed $titolo_de
     */
    public function setTitoloDe($titolo_de): void
    {
        $this->titolo_de = $titolo_de;
    }

    /**
     * @param mixed $titolo_fr
     */
    public function setTitoloFr($titolo_fr): void
    {
        $this->titolo_fr = $titolo_fr;
    }

    /**
     * @param mixed $titolo_es
     */
    public function setTitoloEs($titolo_es): void
    {
        $this->titolo_es = $titolo_es;
    }

    /**
     * @param mixed $didascalia
     */
    public function setDidascalia($didascalia): void
    {
        $this->didascalia = $didascalia;
    }

    /**
     * @param mixed $didascalia_it
     */
    public function setDidascaliaIt($didascalia_it): void
    {
        $this->didascalia_it = $didascalia_it;
    }

    /**
     * @param mixed $didascalia_en
     */
    public function setDidascaliaEn($didascalia_en): void
    {
        $this->didascalia_en = $didascalia_en;
    }

    /**
     * @param mixed $didascalia_de
     */
    public function setDidascaliaDe($didascalia_de): void
    {
        $this->didascalia_de = $didascalia_de;
    }

    /**
     * @param mixed $didascalia_fr
     */
    public function setDidascaliaFr($didascalia_fr): void
    {
        $this->didascalia_fr = $didascalia_fr;
    }

    /**
     * @param mixed $didascalia_es
     */
    public function setDidascaliaEs($didascalia_es): void
    {
        $this->didascalia_es = $didascalia_es;
    }

    /**
     * @param mixed $ordine
     */
    public function setOrdine($ordine): void
    {
        $this->ordine = $ordine;
    }

    /**
     * @param mixed $data_ins
     */
    public function setDataIns($data_ins): void
    {
        $this->data_ins = $data_ins;
    }

    
}