<?php
namespace Categorie;

use Categorie\Model\Categoria;
use File\FileManager;
use Utils\Log;
use Utils\Utils;

class CategorieManager
{
    const ID_MODULO = 6; //corrisponde all'id della tabella tb_moduli
    /**
     * @var \PDO
     */
    protected $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getCategoria($lang,$id_cat)
    {
        $query = "SELECT * FROM tb_categorie WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id_cat,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $categoria = $this->getCategoriaObject($lang,$rows[0]);
                return $categoria;
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
        return false;
    }

    public function getCategorieVariabili($lang, $id, $modulo = null, $id_genitore = null, $visibile = null, $stato = 1)
    {
        $categorie = [];

        $query = "SELECT * FROM tb_categorie WHERE stato = :stato AND id >:id ";
        if($modulo != null){ $query.= " AND modulo=:modulo ";}
        if($id_genitore != null){ $query.= ' AND id_genitore=:id_genitore ';}
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);
        if($modulo != null){ $sql->bindParam(':modulo',$modulo,\PDO::PARAM_INT);}
        if($id_genitore != null){ $sql->bindParam(':id_genitore',$id_genitore,\PDO::PARAM_INT);}
        if($visibile != null){ $sql->bindParam(':visibile',$visibile, \PDO::PARAM_INT);}


        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $categorie[] = $this->getCategoriaObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $categorie;
    }

    public function getCategorie($lang, $modulo = null, $id_genitore = null, $visibile = null, $stato = 1, $limit = null, $offset = null)
    {
        $categorie = [];

        $query = "SELECT * FROM tb_categorie WHERE stato = :stato ";
        if($modulo != null){ $query.= " AND modulo=:modulo ";}
        if($id_genitore != null){ $query.= ' AND id_genitore=:id_genitore ';}
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}


        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        if($modulo != null){ $sql->bindParam(':modulo',$modulo,\PDO::PARAM_STR);}
        if($id_genitore != null){ $sql->bindParam(':id_genitore',$id_genitore,\PDO::PARAM_INT);}
        if($visibile != null){ $sql->bindParam(':visibile',$visibile, \PDO::PARAM_INT);}
        if($offset != null){ $sql->bindParam(':offset',$offset);}

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $categorie[] = $this->getCategoriaObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $categorie;
    }

    public function getAltreCategorie($id, $lang, $modulo = null, $id_genitore = null, $visibile = null, $stato = 1, $limit = null, $offset = null)
    {
        $categorie = [];

        $query = "SELECT * FROM tb_categorie WHERE stato = :stato AND id != :id ";
        if($modulo != null){ $query.= " AND modulo=:modulo ";}
        if($id_genitore != null){ $query.= ' AND id_genitore=:id_genitore ';}
        if($visibile != null){ $query.= ' AND visibile=:visibile ';}
        if($limit != null){ $query.= " LIMIT $limit";}
        if($offset != null) { $query.= " OFFSET :offset ";}

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$id,\PDO::PARAM_INT);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        if($modulo != null){ $sql->bindParam(':modulo',$modulo,\PDO::PARAM_INT);}
        if($id_genitore != null){ $sql->bindParam(':id_genitore',$id_genitore,\PDO::PARAM_INT);}
        if($visibile != null){ $sql->bindParam(':visibile',$visibile, \PDO::PARAM_INT);}
        if($offset != null){ $sql->bindParam(':offset',$offset);}

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $categorie[] = $this->getCategoriaObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $categorie;
    }

    public function getCategoriaObject($lang,$data)
    {
        $categoria   = new Categoria();
        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles($lang,'categorie',$data['id'],1);

        $categoria->setId($data['id']);
        $categoria->setModulo($data['modulo']);
        $categoria->setIdGenitore($data['id_genitore']);
        $categoria->setNome($data['nome_'.$lang]);
        $categoria->setNome($data['nome_'.$lang]);
        $categoria->setNomeIt($data['nome_it']);
        $categoria->setNomeDe($data['nome_de']);
        $categoria->setNomeEn($data['nome_en']);
        $categoria->setNomeFr($data['nome_fr']);
        $categoria->setNomeEs($data['nome_es']);
        $categoria->setDescBreve($data['desc_breve_'.$lang]);
        $categoria->setDescBreveIt($data['desc_breve_it']);
        $categoria->setDescBreveEn($data['desc_breve_en']);
        $categoria->setDescBreveDe($data['desc_breve_de']);
        $categoria->setDescBreveFr($data['desc_breve_fr']);
        $categoria->setDescBreveEs($data['desc_breve_es']);
        $categoria->setDesc($data['desc_'.$lang]);
        $categoria->setDescIt($data['desc_it']);
        $categoria->setDescEn($data['desc_en']);
        $categoria->setDescDe($data['desc_de']);
        $categoria->setDescFr($data['desc_fr']);
        $categoria->setDescEs($data['desc_es']);
        $categoria->setVisibile($data['visibile']);
        $categoria->setDataIns($data['data_ins']);

        if(count($images) > 0)
        {
            $categoria->setImages($images);
            $categoria->setCover($images[0]);
        }

        return $categoria;

    }

    public function updateCategoria($langs,$data)
    {
        $querycampivalore = '';
        foreach ($langs as $lang)
        {
            ${"nome_" . $lang->sigla} = $data['nome_' . $lang->sigla];
            ${"desc_" . $lang->sigla} = isset($data['desc_' . $lang->sigla]) ? $data['desc_' . $lang->sigla]:'';
            ${"desc_breve_" . $lang->sigla} = isset($data['desc_breve_' . $lang->sigla]) ? $data['desc_breve_' . $lang->sigla]:'';


            $querycampivalore .= "nome_" . $lang->sigla . "= :nome_" . $lang->sigla . ",";
            $querycampivalore .= "desc_" . $lang->sigla . "= :desc_" . $lang->sigla . ",";
            $querycampivalore .= "desc_breve_" . $lang->sigla . "= :desc_breve_" . $lang->sigla. ",";
        }
        $modulo = $data['modulo'];
        $querycampivalore .= "modulo=:modulo,";

        $id_categoria = $data['id'];

        $id_genitore = $data['id_cat_genitore'];
        $querycampivalore .= "id_genitore=:id_genitore,";

        $sql = $this->conn->prepare("UPDATE tb_categorie SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
        $sql->bindParam(':id', $id_categoria, \PDO::PARAM_INT);
        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', ${"nome_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', ${"desc_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', ${"desc_breve_" . $lang->sigla}, \PDO::PARAM_STR);

        }
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);
        $sql->bindParam(':id_genitore', $id_genitore, \PDO::PARAM_INT);
        if ($sql->execute() === TRUE)
        {
            if ($sql->rowCount() == 1)
            {

                return 1;
            }
            else
            {
                return 2;
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);

            return false;
        }
    }

    public function getIdElementiCategoria($id_categoria)
    {
        $id_elementi = [];
        $query = "SELECT id_elem FROM tb_categorie_anchor WHERE id_categoria=:id_categoria";
        $sql = $this->conn->prepare($query);

        $sql->bindParam(':id_categoria',$id_categoria,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach ($rows as $row)
                {
                    $id_elementi[] = $row['id_elem'];
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
        return $id_elementi;

    }

    public function getAgganciCategoria($lang,$id_elem,$modulo)
    {
        $categorie = [];
        $query = "SELECT * FROM tb_categorie_anchor WHERE id_elem=:id_elem AND modulo=:modulo";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_elem', $id_elem, \PDO::PARAM_INT);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach ($rows as $row)
                {
                    if($row['id_categoria'] == 0){ continue; }
                    $categorie[] = $this->getCategoria($lang,$row['id_categoria']);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
        return $categorie;
    }

    public function rimuoviCategorie($id_elem, $modulo)
    {
        $query = "DELETE FROM tb_categorie_anchor WHERE id_elem=:id_elem AND modulo=:modulo";
        $sql = $this->conn->prepare($query);

        $sql->bindParam(':id_elem',$id_elem,\PDO::PARAM_INT);
        $sql->bindParam(':modulo',$modulo,\PDO::PARAM_STR);
        if ($sql->execute() === TRUE)
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

    public function agganciaCategoria($id_categoria,$id_elem,$modulo)
    {
        $querycampi = "";
        $queryvalori = "";

        $querycampi .= "id_categoria,";
        $queryvalori .= ":id_categoria,";
        $querycampi .= "id_elem,";
        $queryvalori .= ":id_elem,";
        $querycampi .= "modulo,";
        $queryvalori .= ":modulo,";

        $querycampi = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);
        $query = "INSERT INTO tb_categorie_anchor (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_categoria', $id_categoria, \PDO::PARAM_INT);
        $sql->bindParam(':id_elem', $id_elem, \PDO::PARAM_INT);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);

        if ($sql->execute() === TRUE)
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

    public function eliminaAgganci($id_elem,$modulo)
    {
        $query = "DELETE FROM tb_categorie_anchor WHERE id_elem=:id_elem AND modulo=:modulo";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_elem', $id_elem, \PDO::PARAM_INT);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);

        if ($sql->execute() === TRUE)
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

    public function checkHaveAgganci($id_categoria)
    {
        $query = "SELECT * FROM tb_categorie_anchor WHERE id_categoria=:id_categoria AND stato = 1";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id_categoria', $id_categoria, \PDO::PARAM_INT);

        if ($sql->execute() === TRUE)
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                return true;
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }
        return false;
    }

    public function addCategoria($langs,$data)
    {
        $querycampi = "";
        $queryvalori = "";

        $modulo = $data['modulo'];
        $id_cat_genitore = $data['id_cat_genitore'];

        $querycampi .= "modulo,";
        $queryvalori .= ":modulo,";
        $querycampi .= "id_genitore,";
        $queryvalori .= ":id_genitore,";

        foreach ($langs as $lang)
        {
            $querycampi .= "nome_" . $lang->sigla . ",";
            $queryvalori .= ":nome_" . $lang->sigla . ",";
            $querycampi .= "desc_" . $lang->sigla . ",";
            $queryvalori .= ":desc_" . $lang->sigla . ",";
            $querycampi .= "desc_breve_" . $lang->sigla . ",";
            $queryvalori .= ":desc_breve_" . $lang->sigla . ",";
        }

        $querycampi = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);
        $query = "INSERT INTO tb_categorie (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);
        $sql->bindParam(':id_genitore', $id_cat_genitore, \PDO::PARAM_INT);

        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', $data["nome_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', $data["desc_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', $data["desc_breve_" . $lang->sigla], \PDO::PARAM_STR);
        }

        if ($sql->execute() === TRUE)
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

    public function changeVisibility($id,$visibile)
    {
        $query = "UPDATE tb_categorie SET visibile =:visibile WHERE id =:id";
        $sql = $this->conn->prepare($query);
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

    public function changeStato($id,$stato)
    {
        $query = "UPDATE tb_categorie SET stato =:stato WHERE id =:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
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
}