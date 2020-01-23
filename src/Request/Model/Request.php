<?php
namespace Request\Model;

class Request
{
    public $id = null;
    public $id_seo;
    public $lang;
    public $controller;
    public $action;
    public $dbParams;
    public $paramsPost = [];
    public $paramsGet  = [];
    public $params     = [];
    public $no_index   = 1;
    public $dominio;
    public $url;
    public $uri;
    public $label;


    public function __construct()
    {
        $this->params     = $_REQUEST;
        $this->paramsPost = $_POST;
        $this->paramsGet  = $_GET;
    }

    public function setController($controller)
    {
        $this->controller = $controller;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setNoIndex(bool $no_index)
    {
        $this->no_index = $no_index;
    }

    /**
     * @param mixed $id_seo
     */
    public function setIdSeo($id_seo): void
    {
        $this->id_seo = $id_seo;
    }

    /**
     * @param mixed $dbParams
     */
    public function setDbParams($dbParams): void
    {
        $this->dbParams = $dbParams;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @param mixed $dominio
     */
    public function setDominio($dominio): void
    {
        $this->dominio = $dominio;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }

    /**
     * @param mixed $uri
     */
    public function setUri($uri): void
    {
        $this->uri = $uri;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label): void
    {
        $this->label = $label;
    }

    public function setParamsPost($params)
    {
        $this->paramsPost = $params;
        $this->params = array_merge($this->params,$params);
    }

    public function setParam($key,$value,$type = 'get')
    {
        if($type == 'get')
        {
            $this->paramsGet[$key] = $value;
            $this->params[$key]    = $value;
        }
        elseif($type == 'post')
        {
            $this->paramsPost[$key] = $value;
            $this->params[$key]     = $value;
        }
    }

    public function setParamsGet($params)
    {
        $this->paramsGet = $params;
        $this->params    = array_merge($this->params,$params);
    }


    public function getParamsGet()
    {
        return $this->paramsGet;
    }

    public function getParamsPost()
    {
        return $this->paramsPost;
    }

    public function getParamFromGet($param,$default = null)
    {
        if(isset($this->paramsGet[$param]))
        {
            return $this->paramsGet[$param];
        }
        return $default;
    }

    public function getParamFromPost($param,$default = null)
    {
        if(isset($this->paramsPost[$param]))
        {
            return $this->paramsPost[$param];
        }
        return $default;
    }

    public function getParam($param, $default = null)
    {
        if(isset($this->params[$param]))
        {
            return $this->params[$param];
        }
        return $default;
    }

    public function exist($param)
    {
        if(isset($this->params[$param]))
        {
            return true;
        }
        return false;
    }

    public function is_empty($param)
    {
        if(isset($this->params[$param]) && !empty($this->params[$param]))
        {
            return false;
        }
        return true;
    }




}