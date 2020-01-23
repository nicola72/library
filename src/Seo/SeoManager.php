<?php
namespace Seo;

use Seo\Model\Seo;
use Request\Model\Request;
use Admin\Config;
use Log\Log;
use \PDO;


class SeoManager
{
    /**
     * @var PDO
     */
    protected $conn;

    /**
     * @var Seo
     */
    protected $seo;

    /**
     * @var Request
     */
    protected $request;

    protected $segnaposto;

    public function __construct($conn, Request $request, $segnaposto = null)
    {
        $this->conn = $conn;
        $this->request = $request;
        $this->segnaposto = $segnaposto;
    }

    public function setSeo($segnaposto = null)
    {
        $db_params = $this->getSeoDataByDb();

        //nel caso non sia stata settato nessun id_request
        // o non trovato record nel db prendo il seo default
        if(!$db_params)
        {
            $this->setDefaultSeo();
            return $this;
        }

        //nel caso ci sia seo tipo "vendita online %_segnaposto_% " con nome prodotto come segnaposto
        $title       = ($segnaposto != null)? str_replace('%_segnaposto_%',$segnaposto,$db_params['title']) : $db_params['title'];
        $h1          = ($segnaposto != null)? str_replace('%_segnaposto_%',$segnaposto,$db_params['h1']) : $db_params['h1'];
        $h2          = ($segnaposto != null)? str_replace('%_segnaposto_%',$segnaposto,$db_params['h2']) : $db_params['h2'];
        $alt         = ($segnaposto != null)? str_replace('%_segnaposto_%',$segnaposto,$db_params['alt']) : $db_params['alt'];
        $description = ($segnaposto != null)? str_replace('%_segnaposto_%',$segnaposto,$db_params['description']) : $db_params['description'];

        $this->seo = new Seo();
        $this->seo->setTitle($title);
        $this->seo->setH1($h1);
        $this->seo->setH2($h2);
        $this->seo->setAlt($alt);
        $this->seo->setDescription($description);
        $this->seo->setKeywords($db_params['keywords']);
        
        return $this;
    }

    public function getSeo()
    {
        if($this->seo == '')
        {
            $this->setDefaultSeo();
        }
        return $this->seo;
    }

    public function getSeoItem($id)
    {
        $query = "SELECT * FROM tb_seo WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                $data = $row[0];

                $seo = new Seo();
                $seo->setId($data['id']);
                $seo->setIdRequest($data['id_request']);
                $seo->setH1($data['h1']);
                $seo->setTitle($data['title']);
                $seo->setDescription($data['description']);
                $seo->setAlt($data['alt']);
                $seo->setH2($data['h2']);
                $seo->setKeywords($data['keywords']);
                return $seo;
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

    public function updateSeo($data)
    {
        $id_seo      = $data['id_seo'];
        $title       = $data['title'];
        $h1          = $data['h1'];
        $description = $data['description'];
        $h2          = $data['h2'];
        $alt         = $data['alt'];
        $keywords    = $data['keywords'];

        $query = "UPDATE 
            tb_seo SET 
            title       =:title,
            h1          =:h1,
            description =:description,
            h2          =:h2,
            alt         =:alt,            
            keywords    =:keywords
            WHERE id    =:id";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id_seo,\PDO::PARAM_INT);
        $sql->bindParam(':title',$title,\PDO::PARAM_STR);
        $sql->bindParam(':h1',$h1,\PDO::PARAM_STR);
        $sql->bindParam(':description',$description,\PDO::PARAM_STR);
        $sql->bindParam(':h2',$h2,\PDO::PARAM_STR);
        $sql->bindParam(':alt',$alt,\PDO::PARAM_STR);
        $sql->bindParam(':keywords',$keywords,\PDO::PARAM_STR);


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

    public function removeItem($id)
    {
        $query = "DELETE FROM tb_seo WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    public function removeItemByIdRequest($id_request)
    {
        $query = "DELETE FROM tb_seo WHERE id_request=:id_request";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_request',$id_request,\PDO::PARAM_INT);

        if($sql->execute() === true)
        {
            return true;
        }
        return false;
    }

    public function getSeoList()
    {
        $query = "
            SELECT a.*,
            b.uri,
            c.nome AS dominio 
            FROM tb_seo AS a 
            LEFT JOIN tb_request AS b 
            ON a.id_request = b.id 
            LEFT JOIN tb_alias_domini AS c 
            ON b.id_alias = c.id";
        $sql = $this->conn->prepare($query);

        $list = [];

        if ($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $seo = new Seo();
                    $seo->setId($data['id']);
                    $seo->setIdRequest($data['id_request']);
                    $seo->setUrl($data['dominio'].$data['uri']);
                    $seo->setTitle($data['title']);
                    $seo->setAlt($data['alt']);
                    $seo->setDescription($data['description']);
                    $seo->setH1($data['h1']);
                    $seo->setH2($data['h2']);
                    $seo->setKeywords($data['keywords']);

                    $list[] = $seo;
                }
            }
        }
        else
        {
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }

        return $list;
    }

    public function addSeo($data)
    {
        $id_request    = $data['id_request'];
        $title         = $data['title'];
        $h1            = $data['h1'];
        $description   = $data['description'];
        $h2            = $data['h2'];
        $alt           = $data['alt'];
        $keywords      = $data['keywords'];

        $query = "INSERT INTO tb_seo
            (
                id_request,
                title,
                h1,
                description,
                h2,
                alt,
                keywords
            ) 
            VALUES 
            (   
                :id_request,
                :title,
                :h1,
                :description,
                :h2,
                :alt,
                :keywords
            )";

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_request',$id_request,\PDO::PARAM_INT);
        $sql->bindParam(':title',$title,\PDO::PARAM_STR);
        $sql->bindParam(':h1',$h1,\PDO::PARAM_STR);
        $sql->bindParam(':description',$description,\PDO::PARAM_STR);
        $sql->bindParam(':h2',$h2,\PDO::PARAM_STR);
        $sql->bindParam(':alt',$alt,\PDO::PARAM_STR);
        $sql->bindParam(':keywords',$keywords,\PDO::PARAM_STR);

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

    public function updateSeoKeywords($requests,$keywords)
    {
        foreach($requests as $req)
        {
            $id_request = $req->id;
            $sql = $this->conn->prepare("UPDATE tb_seo SET keywords=:keywords WHERE id_request= :id_request ");
            $sql->bindParam(':keywords', $keywords, \PDO::PARAM_STR);
            $sql->bindParam(':id_request', $id_request, \PDO::PARAM_INT);
            $sql->execute();
        }
        return true;
    }

    protected function getSeoDataByDb()
    {
        if($this->request->id == null)
        {
            return false;
        }
        $query = "SELECT * FROM tb_seo WHERE id_request = :id_request";

        $sql = $this->conn->prepare($query);

        $sql->bindParam(':id_request',$this->request->id, \PDO::PARAM_INT);

        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                return $row[0];
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

    protected function setDefaultSeo()
    {
        $seo = new Seo();
        $seo->setTitle(Config::NOME_PROGETTO.' - '.$this->request->controller);
        $seo->setAlt(strtolower(str_replace(' ', '_', Config::NOME_PROGETTO)));
        $this->seo = $seo;
        return $this;
    }
}