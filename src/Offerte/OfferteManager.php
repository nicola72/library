<?php


namespace Offerte;


use Categorie\CategorieManager;
use File\FileManager;
use Offerte\Model\Offerta;
use PDO;
use Utils\Log;
use Utils\Utils;

class OfferteManager
{
    /**
     * @var \PDO
     */
    protected $conn;


    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getOfferte($lang,  $visibile =null, $stato = 1, $limit =null, $offset =null, $order = null)
    {
        $offerte = [];

        $query = "SELECT * FROM tb_offerte WHERE stato =:stato";
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
                    $offerte[] = $this->getOffertaObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $offerte;
    }


    public function getOfferteByCategory($lang, $id_categoria = null, $visibile =null, $stato =1, $limit =null, $offset =null,  $order = null)
    {
        $offerte = [];
        $modulo  = 'offerte';

        $query = "SELECT a.* FROM tb_offerte AS a LEFT JOIN tb_categorie_anchor AS c ON c.id_elem = a.id WHERE a.stato =:stato  AND c.modulo = :modulo ";
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
                    $offerte[] = $this->getOffertaObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $offerte;
    }



    public function getOfferta($lang,$id)
    {
        $query = "SELECT * FROM tb_offerte WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                return $this->getOffertaObject($lang,$rows[0]);
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
    }

    public function addOfferta($langs,$data)
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
        $query = "INSERT INTO tb_offerte (" . $querycampi . ") VALUES (" . $queryvalori . ")";
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
                    $categorieManager->agganciaCategoria($id_cat,$id_item,'offerte');
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

    public function updateOfferta($langs,$data)
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

        $sql = $this->conn->prepare("UPDATE tb_offerte SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
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
            $categorieManager->rimuoviCategorie($id,'offerte');
            //aggancio le categorie nuove
            if(isset($data['id_categorie']))
            {
                foreach($data['id_categorie'] as $id_cat)
                {
                    $categorieManager->agganciaCategoria($id_cat,$id,'offerte');
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

    public function getOffertaObject($lang,$data)
    {
        $offerta = new Offerta();
        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles($lang,'offerte',$data['id'],1);
        $categorieManager = new CategorieManager($this->conn);
        $categorie        = $categorieManager->getAgganciCategoria($lang,$data['id'],'offerte');

        $offerta->setId($data['id']);
        $offerta->setNome($data['nome_'.$lang]);
        $offerta->setNomeIt($data['nome_it']);
        $offerta->setNomeDe($data['nome_de']);
        $offerta->setNomeEn($data['nome_en']);
        $offerta->setNomeFr($data['nome_fr']);
        $offerta->setNomeEs($data['nome_es']);
        $offerta->setDescBreve($data['desc_breve_'.$lang]);
        $offerta->setDescBreveIt($data['desc_breve_it']);
        $offerta->setDescBreveEn($data['desc_breve_en']);
        $offerta->setDescBreveDe($data['desc_breve_de']);
        $offerta->setDescBreveFr($data['desc_breve_fr']);
        $offerta->setDescBreveEs($data['desc_breve_es']);
        $offerta->setDesc($data['desc_'.$lang]);
        $offerta->setDescIt($data['desc_it']);
        $offerta->setDescEn($data['desc_en']);
        $offerta->setDescDe($data['desc_de']);
        $offerta->setDescFr($data['desc_fr']);
        $offerta->setDescEs($data['desc_es']);
        $offerta->setStato($data['stato']);
        $offerta->setVisibile($data['visibile']);
        $offerta->setDataFine($data['data_fine']);
        $offerta->setDataIns($data['data_ins']);

        $offerta->setCategorie($categorie);

        if(count($images) > 0)
        {
            $offerta->setImages($images);
            $offerta->setCover($images[0]);
        }

        return $offerta;
    }
}