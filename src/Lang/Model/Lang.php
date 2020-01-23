<?php
namespace Lang\Model;


class Lang
{
    public $id = 1;
    public $sigla = 'it';
    public $sigla_long = 'ita';
    public $iso = 'it_IT';
    public $label = 'italiano';
    public $icona_site = 'img/ita.png';
    public $icona_cms = 'img/ita.png';
    public $stato;

    public function __construct()
    {
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setSigla($sigla)
    {
        $this->sigla = $sigla;
    }

    public function setSiglaLong($sigla)
    {
        $this->sigla_long = $sigla;
    }

    public function setIso($iso)
    {
        $this->iso = $iso;
    }

    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function setIcona_site($icona)
    {
        $this->icona_site = $icona;
    }

    public function setIcona_cms($icona)
    {
        $this->icona_cms = $icona;
    }

    public function setStato($stato)
    {
        $this->stato = $stato;
    }


}