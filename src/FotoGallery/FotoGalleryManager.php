<?php
namespace FotoGallery;

use Categorie\CategorieManager;
use File\FileManager;
use FotoGallery\Model\FotoGallery;
use PDO;
use Utils\Log;
use Utils\Utils;

class FotoGalleryManager
{
    /**
     * @var \PDO
     */
    protected $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getFotoGalleries($lang, $visibile = null, $stato = 1, $limit = null, $offset = null)
    {
        $items = [];

        $query = "SELECT * FROM tb_fotogallery WHERE stato = :stato ";
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        if($visibile != null){ $sql->bindParam(':visibile',$visibile, \PDO::PARAM_INT);}
        if($offset != null){ $sql->bindParam(':offset',$offset);}

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $items[] = $this->getFotoGalleryObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $items;
    }


    public function getFotoGalleryByCategory($lang, $id_categoria, $visibile = null, $stato = 1, $limit = null, $offset = null)
    {
        $items = [];
        $modulo = 'fotogallery';

        $query = "
            SELECT a.* 
            FROM tb_fotogallery AS a
            LEFT JOIN tb_categorie_anchor AS b
            ON b.id_elem = a.id
            WHERE stato = :stato 
            AND b.id_categoria = :id_categoria
            AND b.modulo = :modulo
            ";
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}
        $query.= " GROUP BY a.id";
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        $sql->bindParam(':id_categoria',$id_categoria, \PDO::PARAM_INT);
        $sql->bindParam(':modulo',$modulo, \PDO::PARAM_STR);
        if($visibile != null){ $sql->bindParam(':visibile',$visibile, \PDO::PARAM_INT);}
        if($offset != null){ $sql->bindParam(':offset',$offset);}

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $items[] = $this->getFotoGalleryObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $items;
    }

    public function getFotoGallery($lang, $id)
    {
        $query = "SELECT * FROM tb_fotogallery WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                return $this->getFotoGalleryObject($lang,$rows[0]);
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

    }

    public function addFotoGallery($langs,$data)
    {
        $querycampi = "";
        $queryvalori = "";

        foreach ($langs as $lang)
        {
            $querycampi .= "nome_" . $lang->sigla . ",";
            $queryvalori .= ":nome_" . $lang->sigla . ",";
            $querycampi .= "desc_" . $lang->sigla . ",";
            $queryvalori .= ":desc_" . $lang->sigla . ",";
        }

        $querycampi = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);
        $query = "INSERT INTO tb_fotogallery (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql = $this->conn->prepare($query);

        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', $data["nome_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', $data["desc_" . $lang->sigla], \PDO::PARAM_STR);
        }

        if ($sql->execute() === TRUE)
        {
            $id_item = $this->conn->lastInsertId();

            if(isset($data['id_categorie']))
            {
                //aggancio le categorie
                $categorieManager = new CategorieManager($this->conn);
                foreach($data['id_categorie'] as $id_cat)
                {
                    $categorieManager->agganciaCategoria($id_cat,$id_item,'fotogallery');
                }
            }

            return true;
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);

            return false;
        }
    }

    public function updateFotoGallery($langs,$data)
    {
        $id = $data['id'];
        $querycampivalore = '';
        foreach ($langs as $lang)
        {
            ${"nome_" . $lang->sigla} = $data['nome_' . $lang->sigla];
            ${"desc_" . $lang->sigla} = $data['desc_' . $lang->sigla];

            $querycampivalore .= "nome_" . $lang->sigla . "= :nome_" . $lang->sigla . ",";
            $querycampivalore .= "desc_" . $lang->sigla . "= :desc_" . $lang->sigla . ",";
        }

        $sql = $this->conn->prepare("UPDATE tb_fotogallery SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
        $sql->bindParam(':id', $id, \PDO::PARAM_INT);
        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', ${"nome_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', ${"desc_" . $lang->sigla}, \PDO::PARAM_STR);

        }
        if ($sql->execute() === TRUE)
        {
            if ($sql->rowCount() == 1)
            {
                $result = 1;
            }
            else
            {
                $result = 2;
            }

            $categorieManager = new CategorieManager($this->conn);
            //prima rimuovo le categorie vecchio
            $categorieManager->rimuoviCategorie($id,'fotogallery');
            //aggancio le categorie nuove se ci sono
            if(isset($data['id_categorie']))
            {
                foreach($data['id_categorie'] as $id_cat)
                {
                    $categorieManager->agganciaCategoria($id_cat,$id,'fotogallery');
                }
            }

            return $result;
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);

            return false;
        }
    }


    public function changeVisibility($table,$id,$visibile)
    {
        $query = "UPDATE ".$table." SET visibile =:visibile WHERE id =:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':visibile',$visibile,\PDO::PARAM_INT);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            return true;
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
            return false;
        }
    }

    public function changeHomepage($table,$id,$homepage)
    {
        $query = "UPDATE ".$table." SET homepage =:homepage WHERE id =:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':homepage',$homepage,\PDO::PARAM_INT);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            return true;
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
            return false;
        }
    }


    protected function getFotoGalleryObject($lang,$data)
    {
        $item        = new FotoGallery();
        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles($lang,'fotogallery',$data['id'],1);
        $categorieManager = new CategorieManager($this->conn);
        $categorie        = $categorieManager->getAgganciCategoria($lang,$data['id'],'fotogallery');

        $item->setId($data['id']);
        $item->setNome($data['nome_'.$lang]);
        $item->setNome($data['nome_'.$lang]);
        $item->setNomeIt($data['nome_it']);
        $item->setNomeDe($data['nome_de']);
        $item->setNomeEn($data['nome_en']);
        $item->setNomeFr($data['nome_fr']);
        $item->setNomeEs($data['nome_es']);
        $item->setDesc($data['desc_'.$lang]);
        $item->setDescIt($data['desc_it']);
        $item->setDescEn($data['desc_en']);
        $item->setDescDe($data['desc_de']);
        $item->setDescFr($data['desc_fr']);
        $item->setDescEs($data['desc_es']);
        $item->setVisibile($data['visibile']);
        $item->setDataIns($data['data_ins']);
        $item->setCategorie($categorie);

        if(count($images) > 0)
        {
            $item->setImages($images);
            $item->setCover($images[0]);
        }

        return $item;
    }
}