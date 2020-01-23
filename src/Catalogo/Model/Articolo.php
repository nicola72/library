<?php

namespace Catalogo\Model;

use Catalogo\Model\Prodotto;
use File\Model\File;

class Articolo
{
    public $id;
    public $id_prod;
    /**
     * @var Prodotto
     */
    public $prodotto;
    public $codice; //codice specifico
    public $colore;
    public $taglia;
    public $misura;
    public $confezione;
    public $lunghezza;
    public $larghezza;
    public $altezza;
    public $peso;
    public $numero;
    public $disponibilita;
    public $scorta_min = false;
    public $riordinato;
    public $prezzo;
    public $prezzo_scontato;
    /**
     * @var File
     */
    public $cover;
    public $images = [];


    public function __construct()
    {
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setId_prod($id_prod)
    {
        $this->id_prod = $id_prod;
    }

    public function setProdotto(Prodotto $prodotto)
    {
        $this->prodotto = $prodotto;
    }

    /**
     * @param mixed $codice
     */
    public function setCodice($codice): void
    {
        $this->codice = $codice;
    }

    /**
     * @param mixed $colore
     */
    public function setColore($colore): void
    {
        $this->colore = $colore;
    }

    /**
     * @param mixed $taglia
     */
    public function setTaglia($taglia): void
    {
        $this->taglia = $taglia;
    }

    /**
     * @param mixed $misura
     */
    public function setMisura($misura): void
    {
        $this->misura = $misura;
    }

    /**
     * @param mixed $confezione
     */
    public function setConfezione($confezione): void
    {
        $this->confezione = $confezione;
    }

    /**
     * @param mixed $lunghezza
     */
    public function setLunghezza($lunghezza): void
    {
        $this->lunghezza = $lunghezza;
    }

    /**
     * @param mixed $larghezza
     */
    public function setLarghezza($larghezza): void
    {
        $this->larghezza = $larghezza;
    }

    /**
     * @param mixed $altezza
     */
    public function setAltezza($altezza): void
    {
        $this->altezza = $altezza;
    }

    /**
     * @param mixed $peso
     */
    public function setPeso($peso): void
    {
        $this->peso = $peso;
    }

    /**
     * @param mixed $numero
     */
    public function setNumero($numero): void
    {
        $this->numero = $numero;
    }

    /**
     * @param mixed $disponibilita
     */
    public function setDisponibilita($disponibilita): void
    {
        $this->disponibilita = $disponibilita;
    }

    /**
     * @param bool $scorta_min
     */
    public function setScortaMin(bool $scorta_min): void
    {
        $this->scorta_min = $scorta_min;
    }

    /**
     * @param mixed $riordinato
     */
    public function setRiordinato($riordinato): void
    {
        $this->riordinato = $riordinato;
    }

    /**
     * @param mixed $prezzo
     */
    public function setPrezzo($prezzo): void
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
     * @param File $cover
     */
    public function setCover(File $cover): void
    {
        $this->cover = $cover;
    }

    /**
     * @param array $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    


}
