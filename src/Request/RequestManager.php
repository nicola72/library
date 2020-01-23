<?php
namespace Request;

use Admin\Config;
use Request\Model\Request;
use Request\Model\Alias;
use PDO;
use Utils\Log;
use Utils\Utils;

class RequestManager
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var PDO
     */
    protected $conn;


    protected $root;
    protected $dominio;

    public function __construct(PDO $conn, $root)
    {
        $this->root    = $root;
        $this->dominio = $_SERVER["HTTP_HOST"];
        $this->conn    = $conn;
    }

    public function getSeoUrl($lang, $controller, $action = 'index', $params = null)
    {
        //crea la query string se ci sono parametri
        $query_string = '';
        if($params != null && count($params) > 0)
        {
            $query_string.= '?'.http_build_query($params);
        }

        //cerco l'url con i parametri nel db es.una categoria specifica
        if($query_string != '')
        {
            if($url = $this->getSeoUrlWithParams($lang,$controller,$action,$params))
            {
                return $url;
            }
        }

        $query = "
            SELECT a.*,
            b.nome AS dominio 
            FROM tb_request AS a 
            LEFT JOIN tb_alias_domini AS b 
            ON a.id_alias = b.id 
            WHERE a.lang = :lang 
            AND a.controller = :controller 
            AND a.action = :action ";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':lang',$lang, \PDO::PARAM_STR);
        $sql->bindParam(':controller',$controller, \PDO::PARAM_STR);
        $sql->bindParam(':action',$action, \PDO::PARAM_STR);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();

            if (is_array($row) && count($row) > 0)
            {
                return Config::HTTP_PROTOCOL.'://'.$row[0]['dominio'].$row[0]['uri'].$query_string;
            }
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
        }

        //altrimenti creo una url con lang controller e action
        $url = Config::HTTP_PROTOCOL.'://'.$this->dominio.$this->root.$lang.'/'.$controller.'/'.$action . $query_string;
        return $url;
    }

    protected function getSeoUrlWithParams($lang, $controller, $action, $params)
    {
        $db_params = '';
        foreach($params as $key=>$value)
        {
            $db_params.= $key.'='.$value.',';
        }
        $db_params = Utils::removeLastCaracter($db_params);

        $query = "
                SELECT a.*,
                b.nome AS dominio 
                FROM tb_request AS a 
                LEFT JOIN tb_alias_domini AS b 
                ON a.id_alias = b.id 
                WHERE a.lang = :lang 
                AND a.controller = :controller 
                AND a.action = :action 
                AND a.params = :params";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':lang',$lang, \PDO::PARAM_STR);
        $sql->bindParam(':controller',$controller, \PDO::PARAM_STR);
        $sql->bindParam(':action',$action, \PDO::PARAM_STR);
        $sql->bindParam(':params',$db_params, \PDO::PARAM_STR);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();

            if (is_array($row) && count($row) > 0)
            {
                return Config::HTTP_PROTOCOL.'://'.$row[0]['dominio'].$row[0]['uri'];
            }
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
        }
        return false;
    }

    public function getUrl($lang, $controller, $action = 'index', $params = null)
    {
        //crea la query string se ci sono parametri
        $query_string = '';
        if($params != null && count($params) > 0)
        {
            $query_string.= '?'.http_build_query($params);
        }
        //altrimenti creo una url con lang controller e action
        $url = Config::HTTP_PROTOCOL.'://'.$this->dominio.$this->root.$lang.'/'.$controller.'/'.$action . $query_string;
        return $url;
    }

    public function setRequestByDb()
    {
        // prende l'url della chiamata senza parametri es contatti.php
        $uri = strtok($_SERVER["REQUEST_URI"], '?');
        $query_string = $_SERVER['QUERY_STRING'];

        //caso uri vuota --- cerco la request per dominio secco
        if ($uri == $this->root.'' || $uri == $this->root.'/' || $uri == $this->root.'index.php')
        {
            return $this->setRequestByDomain();
        }

        //caso di URI SCHEDA PRODOTTO
        //ATTENZIONE !!! ricordarsi di configurarla nel pannello seo inserendo nel campo uri es. 'it/scp-'
        if(substr($uri, 4,4) == 'scp-')
        {
            $lang = substr($uri, 1,2);

            $url_array = explode('-', $uri);
            $id = $url_array[1];

            $uri = '/'.$lang.'/scp-';
            if(!$this->setRequestByUrl($uri,''))
            {
                echo 'Attenzione non è stata configurata nel pannello la request con scp-';
                exit();
            }
            $this->request->setParam('id',$id);
            return true;
        }

        //caso di URI SCHEDA ARTICOLO
        //ATTENZIONE !!! ricordarsi di configurarla nel pannello seo inserendo nel campo uri es. 'it/scp-'
        if(substr($uri, 4,4) == 'art-')
        {
            $lang = substr($uri, 1,2);

            $url_array = explode('-', $uri);
            $id = $url_array[1];

            $uri = '/'.$lang.'/art-';

            if(!$this->setRequestByUrl($uri,''))
            {
                echo 'Attenzione non è stata configurata nel pannello la request con art-';
                exit();
            }
            $this->request->setParam('id',$id);
            return true;
        }

        //caso di URI CATEGORIA
        //ATTENZIONE !!! ricordarsi di configurarla nel pannello seo inserendo nel campo uri es. 'it/scp-'
        if(substr($uri, 4,4) == 'ctg-')
        {
            $lang = substr($uri, 1,2);

            $url_array = explode('-', $uri);
            $id = $url_array[1];

            $uri = '/'.$lang.'/ctg-';
            if(!$this->setRequestByUrl($uri,''))
            {
                echo 'Attenzione non è stata configurata nel pannello la request con ctg-';
                exit();
            }
            $this->request->setParam('id',$id);
            return true;
        }

        //caso di URI GENERICA cerco nel db con uri e paramteri
        if($this->setRequestByUrl($uri,$query_string))
        {
            return true;
        }

        //provo con solo la uri senza parametri
        if($this->setRequestByUrlWithoutParams($uri))
        {
            return true;
        }

        //caso di URI NON TROVATA NEL DB
        return $this->setRequestNoDb();
    }

    public function setRequestNoDb()
    {
        // prende l'url della chiamata senza parametri es contatti.php
        $uri        = strtok($_SERVER["REQUEST_URI"], '?');
        $dominio    = $_SERVER['HTTP_HOST'];
        $no_index   = 1;
        $id_request = null;
        $id_seo     = null;

        if ($uri == $this->root.'' || $uri == $this->root.'/' || $uri == $this->root.'index.php')
        {
            $lang       = 'it';
            $controller = 'home';
            $action     = 'index';
        }
        else
        {
            $query_string = $_SERVER['QUERY_STRING'];
            $req_uri      = $_SERVER['REQUEST_URI'];

            if($query_string != '')
            {
                $req_uri = str_replace('?'.$query_string,'',$req_uri);
            }

            $route = explode('/',$req_uri);

            if($this->root == '/')
            {
                $lang       = (isset($route[1]) && $route[1] != '')? $route[1] : 'it';
                $controller = (isset($route[2]) && $route[2] != '')? $route[2] : '404';
                $action     = (isset($route[3]) && $route[3] != '')? $route[3] : 'index';
            }
            else
            {
                $lang       = (isset($route[2]) && $route[2] != '')? $route[2] : 'it';
                $controller = (isset($route[3]) && $route[3] != '')? $route[3] : 'home';
                $action     = (isset($route[4]) && $route[4] != '')? $route[4] : 'index';
            }

        }

        //controllo che non sia un url da fare redirect 301
        $lang_array = ['it','en','de','fr','es','ru'];
        if(!in_array($lang,$lang_array))
        {
            Utils::redirect301(Config::HTTP_PROTOCOL.'://'.$_SERVER['HTTP_HOST']);
        }

        if($controller == '404')
        {
            if(Config::IN_COSTRUZIONE == false)
            {
                Utils::redirect301(Config::HTTP_PROTOCOL.'://'.$_SERVER['HTTP_HOST']);
            }
            echo 'attenzione! nessun controller impostato';
            exit();
        }

        $uri = str_replace($this->root,'/',$uri);

        $this->request = $this->setRequestObject($dominio, $uri, $lang, $controller, $action, false, null, $no_index);
        return true;
    }

    protected function setRequestByUrlWithoutParams($uri)
    {
        $query = "
            SELECT a.*,
            b.nome AS dominio,
            c.id AS id_seo
            FROM tb_request AS a 
            LEFT JOIN tb_alias_domini AS b 
            ON a.id_alias = b.id 
            LEFT JOIN tb_seo AS c 
            ON c.id_request = a.id
            WHERE a.uri = :uri ";



        $sql = $this->conn->prepare($query);

        $sql->bindParam(':uri',$uri, \PDO::PARAM_STR);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                $data = $row[0];
                $lang       = $data['lang'];
                $controller = $data['controller'];
                $action     = $data['action'];
                $id         = $data['id'];
                $params     = $data['params'];
                $dominio    = $data['dominio'];
                $uri        = $data['uri'];
                $no_index   = $data['no_index'];
                $id_seo     = $data['id_seo'];
                $label      = $data['label'];

                $this->request = $this->setRequestObject($dominio, $uri, $lang, $controller, $action, $params, $id, $no_index, $id_seo, $label);
                return true;
            }
            return false;
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }
    }

    protected function setRequestByUrl($uri,$query_string)
    {
        $query = "
            SELECT a.*,
            b.nome AS dominio,
            c.id AS id_seo
            FROM tb_request AS a 
            LEFT JOIN tb_alias_domini AS b 
            ON a.id_alias = b.id 
            LEFT JOIN tb_seo AS c 
            ON c.id_request = a.id
            WHERE a.uri = :uri 
            AND a.params = :params";


        $sql = $this->conn->prepare($query);

        $sql->bindParam(':uri',$uri, \PDO::PARAM_STR);
        $sql->bindParam(':params',$query_string, \PDO::PARAM_STR);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                $data = $row[0];
                $lang       = $data['lang'];
                $controller = $data['controller'];
                $action     = $data['action'];
                $id         = $data['id'];
                $params     = $data['params'];
                $dominio    = $data['dominio'];
                $uri        = $data['uri'];
                $no_index   = $data['no_index'];
                $id_seo     = $data['id_seo'];
                $label      = $data['label'];

                $this->request = $this->setRequestObject($dominio, $uri, $lang, $controller, $action, $params, $id, $no_index, $id_seo, $label);
                return true;
            }
            return false;
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }
    }

    protected function setRequestByDomain()
    {

        $query = "
            SELECT a.*,
            b.nome AS dominio,
            c.id AS id_seo
            FROM tb_request AS a 
            LEFT JOIN tb_alias_domini AS b 
            ON a.id_alias = b.id 
            LEFT JOIN tb_seo AS c 
            ON c.id_request = a.id
            WHERE b.nome = :dominio 
            AND (a.uri = '' OR a.uri IS NULL)";


        $sql = $this->conn->prepare($query);
        $sql->bindParam(':dominio',$this->dominio, \PDO::PARAM_STR);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                $data = $row[0];
                $lang       = $data['lang'];
                $controller = $data['controller'];
                $action     = $data['action'];
                $id         = $data['id'];
                $no_index   = $data['no_index'];
                $dominio    = $data['dominio'];
                $uri        = $data['uri'];
                $id_seo     = $data['id_seo'];
                $label      = $data['label'];

                $this->request = $this->setRequestObject($dominio, $uri, $lang, $controller, $action,false, $id, $no_index, $id_seo, $label);
                return true;

            }
            return $this->setRequestNoDb();
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }
    }

    private function setRequestObject($dominio, $uri, $lang, $controller, $action, $params=false, $id=null, $no_index=true, $id_seo=null, $label = null)
    {
        $request = new Request();

        $request->setDominio($dominio);
        $request->setUri($uri);
        $request->setUrl($dominio.$uri);
        $request->setLang($lang);
        $request->setController($controller);
        $request->setAction($action);
        $request->setId($id);
        $request->setNoIndex($no_index);
        $request->setIdSeo($id_seo);
        $request->setLabel($label);
        if($params)
        {
            $request->setDbParams($params); //parametri in stringa del db
            $paramsStringArr = explode(',', $params);

            //nel ci siano dei parametri specifici li vado a settare nella request
            foreach($paramsStringArr as $string)
            {
                $param = explode('=', $string);
                $request->setParam($param[0],$param[1]);
            }
        }
        return $request;
    }

    public function getRequestsInDb($lang = false)
    {
        $requests = [];

        $query = "
                SELECT a.*,
                b.nome AS dominio,
                c.id AS id_seo
                FROM tb_request AS a 
                LEFT JOIN tb_alias_domini AS b 
                ON a.id_alias = b.id 
                LEFT JOIN tb_seo AS c 
                ON c.id_request = a.id 
                WHERE a.stato = 1 ";
        if($lang)
        {
            $query.= " AND a.lang=:lang";
        }

        $sql = $this->conn->prepare($query);
        if($lang)
        {
            $sql->bindParam(':lang',$lang,\PDO::PARAM_STR);
        }
        if ($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {

                    $lang       = $data['lang'];
                    $controller = $data['controller'];
                    $action     = $data['action'];
                    $params     = $data['params'];
                    $id         = $data['id'];
                    $no_index   = $data['no_index'];
                    $dominio    = $data['dominio'];
                    $uri        = $data['uri'];
                    $id_seo     = $data['id_seo'];
                    $label      = $data['label'];
                    $requests[] = $this->setRequestObject($dominio, $uri, $lang, $controller, $action, $params, $id, $no_index, $id_seo, $label);
                }

            }
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
        }

        return $requests;
    }

    public function getAlias($stato = true)
    {
        $alias_arr = [];

        if($stato)
        {
            $query = "SELECT * FROM tb_alias_domini WHERE stato = 1";
        }
        else
        {
            $query = "SELECT * FROM tb_alias_domini";
        }

        $sql = $this->conn->prepare($query);

        if ($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $alias = new Alias();
                    $alias->setId($data['id']);
                    $alias->setDominio($data['nome']);
                    $alias_arr[] = $alias;
                }
            }
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
        }

        return $alias_arr;
    }

    public function getRequestById($id)
    {

        $query = "
                SELECT a.*,
                b.nome AS dominio,
                c.id AS id_seo
                FROM tb_request AS a 
                LEFT JOIN tb_alias_domini AS b 
                ON a.id_alias = b.id 
                LEFT JOIN tb_seo AS c 
                ON c.id_request = a.id 
                WHERE a.id = :id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            $data = $rows[0];

            $lang       = $data['lang'];
            $controller = $data['controller'];
            $action     = $data['action'];
            $params     = $data['params'];
            $id         = $data['id'];
            $no_index   = $data['no_index'];
            $dominio    = $data['dominio'];
            $uri        = $data['uri'];
            $id_seo     = $data['id_seo'];
            $label      = $data['label'];
            return $this->setRequestObject($dominio, $uri, $lang, $controller, $action, $params, $id, $no_index, $id_seo, $label);
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
        }
    }

    public function updateRequest($data)
    {
        $id_request = $data['id_request'];
        $id_alias   = $data['id_alias'];
        $uri        = $data['uri'];
        $lang       = $data['lang'];
        $controller = $data['controller'];
        $action     = $data['action'];
        $params     = $data['params'];
        $label      = $data['label'];

        $query = "UPDATE 
            tb_request SET 
            id_alias    =:id_alias,
            uri         =:uri,
            lang        =:lang,
            controller  =:controller,
            action      =:action,
            params      =:params,
            label       =:label
            WHERE id= :id";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id_request,\PDO::PARAM_INT);
        $sql->bindParam(':id_alias',$id_alias,\PDO::PARAM_INT);
        $sql->bindParam(':uri',$uri,\PDO::PARAM_STR);
        $sql->bindParam(':lang',$lang,\PDO::PARAM_STR);
        $sql->bindParam(':controller',$controller,\PDO::PARAM_STR);
        $sql->bindParam(':action',$action,\PDO::PARAM_STR);
        $sql->bindParam(':params',$params,\PDO::PARAM_STR);
        $sql->bindParam(':label',$label,\PDO::PARAM_STR);

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);


            return false;
        }
    }

    public function addRequest($data)
    {
        $id_alias = $data['id_alias'];
        $uri = $data['uri'];
        $lang =$data['lang'];
        $controller = $data['controller'];
        $action = $data['action'];
        $params = $data['params'];
        $label = $data['label'];

        $query = "INSERT INTO tb_request 
            (
                id_alias,
                uri,
                lang,
                controller,
                action,
                params,
                label
            ) 
            VALUES 
            (   
                :id_alias,
                :uri,
                :lang,
                :controller,
                :action,
                :params,
                :label
            )";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_alias',$id_alias,\PDO::PARAM_INT);
        $sql->bindParam(':uri',$uri,\PDO::PARAM_STR);
        $sql->bindParam(':lang',$lang,\PDO::PARAM_STR);
        $sql->bindParam(':controller',$controller,\PDO::PARAM_STR);
        $sql->bindParam(':action',$action,\PDO::PARAM_STR);
        $sql->bindParam(':params',$params,\PDO::PARAM_STR);
        $sql->bindParam(':label',$label,\PDO::PARAM_STR);

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);


            return false;
        }
    }

    public function removeRequest($id)
    {
        $query = "DELETE FROM tb_request WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    public function updateNoIndex($id_request,$no_index = 0)
    {
        $query = "UPDATE 
            tb_request SET 
            no_index= :no_index
            WHERE id= :id";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id_request,\PDO::PARAM_INT);
        $sql->bindParam(':no_index',$no_index,\PDO::PARAM_INT);

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);


            return false;
        }
    }


    public function getAliasOne($id_alias)
    {
        $query = "SELECT * FROM tb_alias_domini WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam('id',$id_alias,\PDO::PARAM_INT);

        if($sql->execute() === true)
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                $data  = $row[0];
                $alias = new Alias();
                $alias->setId($data['id']);
                $alias->setDominio($data['nome']);
                return $alias;
            }
            return false;
        }
        return false;
    }

    public function updateAlias($data)
    {
        $id_alias = $data['id_alias'];
        $nome = $data['nome'];
        $query = "UPDATE tb_alias_domini SET nome=:nome WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':nome',$nome,\PDO::PARAM_STR);
        $sql->bindParam('id',$id_alias,\PDO::PARAM_INT);
        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    public function removeAlias($id_alias)
    {
        $query = "DELETE FROM tb_alias_domini WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id_alias,\PDO::PARAM_INT);

        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    public function addAlias($data)
    {
        $nome = $data['nome'];
        $query = "INSERT into tb_alias_domini (nome) VALUES (:nome)";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':nome',$nome,\PDO::PARAM_STR);
        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}