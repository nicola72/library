<?php
namespace News;

use Categorie\CategorieManager;
use File\FileManager;
use News\Model\News;
use PDO;
use Utils\Log;
use Utils\Utils;

class NewsManager
{
    /**
     * @var \PDO
     */
    protected $conn;


    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getNews($lang,  $visibile =null, $stato = 1, $limit =null, $offset =null, $order = null)
    {
        $news = [];

        $query = "SELECT * FROM tb_news WHERE stato =:stato";
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}
        if($order != null)
        {
            $query.= " ORDER BY :order ";
        }
        else
        {
            $query.= " ORDER BY id ASC ";
        }
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}


        $sql = $this->conn->prepare($query);

        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);

        if($visibile != null)
        {
            $sql->bindParam('visibile',$visibile,\PDO::PARAM_INT);
        }
        if($offset != null)
        {
            $sql->bindParam('offset',$offset,\PDO::PARAM_INT);
        }
        if($order != null)
        {
            $sql->bindParam('order',$order,\PDO::PARAM_STR);
        }

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $news[] = $this->getNewsObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $news;
    }


    public function getNewsByCategory($lang, $id_categoria = null, $visibile =null, $stato =1, $limit =null, $offset =null,  $order = null)
    {
        $news = [];
        $modulo = 'news';

        $query = "SELECT a.* FROM tb_news AS a LEFT JOIN tb_categorie_anchor AS c ON c.id_elem = a.id WHERE a.stato =:stato  AND c.modulo = :modulo ";
        if($visibile != null)
        {
            $query .= ' AND a.visibile =:visibile ';
        }
        if($id_categoria != null)
        {
            $query .= " AND c.id_categoria =:id_categoria ";
        }

        $query.= " GROUP BY a.id";
        if($order != null)
        {
            $query.= " ORDER BY :order ";
        }
        else
        {
            $query.= " ORDER BY id ASC ";
        }
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}

        $sql = $this->conn->prepare($query);

        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        $sql->bindParam(':modulo',$modulo,\PDO::PARAM_STR);

        if($id_categoria != null)
        {
            $sql->bindParam(':id_categoria',$id_categoria,\PDO::PARAM_INT);
        }

        if($visibile != null)
        {
            $sql->bindParam('visibile',$visibile,\PDO::PARAM_INT);
        }
        if($offset != null)
        {
            $sql->bindParam('offset',$offset,\PDO::PARAM_INT);
        }
        if($order != null)
        {
            $sql->bindParam('order',$order,\PDO::PARAM_STR);
        }

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $news[] = $this->getNewsObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $news;
    }



    public function getNewsItem($lang,$id)
    {
        $query = "SELECT * FROM tb_news WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                return $this->getNewsObject($lang,$rows[0]);
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
    }

    public function addNews($langs,$data)
    {
        $querycampi = "";
        $queryvalori = "";

        foreach ($langs as $lang)
        {
            $querycampi .= "nome_" . $lang->sigla . ",";
            $queryvalori .= ":nome_" . $lang->sigla . ",";
            $querycampi .= "desc_" . $lang->sigla . ",";
            $queryvalori .= ":desc_" . $lang->sigla . ",";
            $querycampi .= "desc_breve_" . $lang->sigla . ",";
            $queryvalori .= ":desc_breve_" . $lang->sigla . ",";
        }

        if(isset($data['data_fine']) && $data['data_fine']!= '')
        {
            $querycampi .= "data_fine ,";
            $queryvalori .= ":data_fine ,";
        }

        $querycampi = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);
        $query = "INSERT INTO tb_news (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql = $this->conn->prepare($query);

        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', $data["nome_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', $data["desc_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', $data["desc_breve_" . $lang->sigla], \PDO::PARAM_STR);
        }

        if(isset($data['data_fine']) && $data['data_fine']!= '')
        {
            $data_fine = Utils::formatDateForDb($data['data_fine']);
            $sql->bindParam(':data_fine', $data_fine, \PDO::PARAM_STR);
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
                    $categorieManager->agganciaCategoria($id_cat,$id_item,'news');
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

    public function updateNews($langs,$data)
    {
        $id = $data['id'];
        $querycampivalore = '';
        foreach ($langs as $lang)
        {
            ${"nome_" . $lang->sigla} = $data['nome_' . $lang->sigla];
            ${"desc_" . $lang->sigla} = isset($data['desc_' . $lang->sigla]) ? $data['desc_' . $lang->sigla] : '';
            ${"desc_breve_" . $lang->sigla} = isset($data['desc_breve_' . $lang->sigla]) ? $data['desc_breve_' . $lang->sigla] : '';

            $querycampivalore .= "nome_" . $lang->sigla . "= :nome_" . $lang->sigla . ",";
            $querycampivalore .= "desc_" . $lang->sigla . "= :desc_" . $lang->sigla . ",";
            $querycampivalore .= "desc_breve_" . $lang->sigla . "= :desc_breve_" . $lang->sigla . ",";
        }

        if(isset($data['data_fine']))
        {
            $querycampivalore .= "data_fine= :data_fine,";
        }

        $sql = $this->conn->prepare("UPDATE tb_news SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
        $sql->bindParam(':id', $id, \PDO::PARAM_INT);
        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', ${"nome_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', ${"desc_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', ${"desc_breve_" . $lang->sigla}, \PDO::PARAM_STR);

        }

        if(isset($data['data_fine']))
        {
            $data_fine = ($data['data_fine'] != '') ? Utils::formatDateForDb($data['data_fine']) : null;

            if($data_fine == null)
            {
                $sql->bindParam(':data_fine', $data_fine, \PDO::PARAM_NULL);
            }
            else
            {
                $sql->bindParam(':data_fine', $data_fine, \PDO::PARAM_STR);
            }
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
            $categorieManager->rimuoviCategorie($id,'news');
            //aggancio le categorie nuove
            if(isset($data['id_categorie']))
            {
                foreach($data['id_categorie'] as $id_cat)
                {
                    $categorieManager->agganciaCategoria($id_cat,$id,'news');
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

    public function getNewsObject($lang,$data)
    {
        $news = new News();
        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles($lang,'news',$data['id'],1);
        $categorieManager = new CategorieManager($this->conn);
        $categorie        = $categorieManager->getAgganciCategoria($lang,$data['id'],'news');

        $news->setId($data['id']);
        $news->setNome($data['nome_'.$lang]);
        $news->setNomeIt($data['nome_it']);
        $news->setNomeDe($data['nome_de']);
        $news->setNomeEn($data['nome_en']);
        $news->setNomeFr($data['nome_fr']);
        $news->setNomeEs($data['nome_es']);
        $news->setDescBreve($data['desc_breve_'.$lang]);
        $news->setDescBreveIt($data['desc_breve_it']);
        $news->setDescBreveEn($data['desc_breve_en']);
        $news->setDescBreveDe($data['desc_breve_de']);
        $news->setDescBreveFr($data['desc_breve_fr']);
        $news->setDescBreveEs($data['desc_breve_es']);
        $news->setDesc($data['desc_'.$lang]);
        $news->setDescIt($data['desc_it']);
        $news->setDescEn($data['desc_en']);
        $news->setDescDe($data['desc_de']);
        $news->setDescFr($data['desc_fr']);
        $news->setDescEs($data['desc_es']);
        $news->setStato($data['stato']);
        $news->setVisibile($data['visibile']);
        $news->setDataFine($data['data_fine']);
        $news->setDataIns($data['data_ins']);

        $news->setCategorie($categorie);

        if(count($images) > 0)
        {
            $news->setImages($images);
            $news->setCover($images[0]);
        }

        return $news;
    }




}