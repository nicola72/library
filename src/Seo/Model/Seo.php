<?php
namespace Seo\Model;


class Seo
{
    public $id;
    public $id_request;
    public $url;
    public $h1;
    public $title;
    public $description;
    public $h2;
    public $alt;
    public $keywords;

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setIdRequest($id)
    {
        $this->id_request = $id;
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setH1($h1)
    {
        $this->h1 = $h1;
        return $this;
    }

    public function setH2($h2)
    {
        $this->h2 = $h2;
        return $this;
    }

    public function setAlt($alt)
    {
        $this->alt = $alt;
        return $this;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
        return $this;
    }
}