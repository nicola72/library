<?php
namespace Catalogo;

use Categorie\CategorieManager;
use DataTable\SSDataTable;
use File\FileManager;
use Catalogo\Model\Prodotto;
use Catalogo\Model\Tag;
use Catalogo\Model\TipoTag;
use Catalogo\Model\Marca;
use PDO;
use Utils\Log;
use Utils\Utils;

class CatalogoManager
{
    /**
     * @var \PDO
     */
    protected $conn;


    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getProdotti($lang,  $visibile =null, $stato = 1, $limit =null, $offset =null, $order = null)
    {
        $prodotti = [];

        $query = "SELECT * FROM tb_prodotti WHERE stato =:stato";
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
                    $prodotti[] = $this->getProdottoObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $prodotti;
    }

    public function getProdottiByCategory($lang, $id_categoria = null, $visibile =null, $stato =1, $limit =null, $offset =null,  $order = null)
    {
        $prodotti = [];
        $modulo  = 'prodotti';

        $query = "SELECT a.* FROM tb_prodotti AS a LEFT JOIN tb_categorie_anchor AS c ON c.id_elem = a.id WHERE a.stato =:stato  AND c.modulo = :modulo ";
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
                    $prodotti[] = $this->getProdottoObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $prodotti;
    }

    public function getProdottiHomepage($lang, $limit = null, $id_categoria = null, $order = null)
    {
        $prodotti = [];
        $modulo  = 'prodotti';

        $query = "SELECT a.* FROM tb_prodotti AS a LEFT JOIN tb_categorie_anchor AS c ON c.id_elem = a.id WHERE a.stato = 1 AND a.homepage = 1 AND a.visibile = 1  AND c.modulo = :modulo ";

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



        $sql = $this->conn->prepare($query);

        $sql->bindParam(':modulo',$modulo,\PDO::PARAM_STR);

        if($id_categoria != null)
        {
            $sql->bindParam(':id_categoria',$id_categoria,\PDO::PARAM_INT);
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
                    $prodotti[] = $this->getProdottoObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $prodotti;
    }

    public function getProdottiByString($lang, $search)
    {
        $prodotti = [];

        $query = "
            SELECT a.* FROM tb_prodotti AS a
            WHERE a.stato = 1 
            AND a.visibile = 1 
            AND (a.nome_it LIKE :nome OR a.desc_it LIKE :descrizione )";

        $nome = '%'.$search.'%';
        $desc = '%'.$search.'%';

        $sql = $this->conn->prepare($query);
        $sql->bindParam(':nome',$nome,\PDO::PARAM_STR);
        $sql->bindParam(':descrizione',$desc,\PDO::PARAM_STR);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $prodotti[] = $this->getProdottoObject($lang,$data);
                }
            }
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $prodotti;
    }

    public function getMarche($stato = 1)
    {
        $marche = [];

        $query = "SELECT * FROM tb_marche WHERE stato = 1";
        $sql = $this->conn->prepare($query);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $marche[] = $this->getMarcaObject($data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $marche;
    }

    public function getMarca($id)
    {
        $query = "SELECT * FROM tb_marche WHERE id=:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $item = $this->getMarcaObject($rows[0]);
                return $item;
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

    public function getTags($lang)
    {
        $tags = [];

        $query = "SELECT * FROM tb_tags WHERE stato = 1";
        $sql = $this->conn->prepare($query);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach($rows as $data)
                {
                    $tags[] = $this->getTagObject($lang,$data);
                }
            }
        }
        else
        {
            //$sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
            Log::queryError($message,$sql);
        }

        return $tags;
    }

    public function getTipiTags($lang, $stato = 1)
    {

    }

    public function getTag($lang,$id)
    {
        $query = "SELECT * FROM tb_tags WHERE id=:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $item = $this->getTagObject($lang,$rows[0]);
                return $item;
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

    public function getTipoTag($lang,$id)
    {
        $query = "SELECT * FROM tb_tipi_tags WHERE id=:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $item = $this->getTipoTagObject($lang,$rows[0]);
                return $item;
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

    public function getProdotto($lang,$id)
    {
        $query = "SELECT * FROM tb_prodotti WHERE id=:id";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':id',$id,\PDO::PARAM_INT);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $item = $this->getProdottoObject($lang,$rows[0]);
                return $item;
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

    public function updatePrezziProdotto($id_prodotto,$prezzo,$prezzo_scontato)
    {
        $query = "UPDATE tb_prodotti SET prezzo= :prezzo, prezzo_scontato= :prezzo_scontato WHERE id=:id";
        $sql = $this->conn->prepare($query);

        $prezzo_scontato = ($prezzo_scontato != null) ? $prezzo_scontato : 0;

        $sql->bindParam(':id', $id_prodotto, \PDO::PARAM_INT);
        $sql->bindParam(':prezzo', $prezzo, \PDO::PARAM_STR);
        $sql->bindParam(':prezzo_scontato', $prezzo_scontato, \PDO::PARAM_STR);

        if($sql->execute())
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

    public function addMarca($data)
    {
        $querycampi  = "";
        $queryvalori = "";

        $nome = $data['nome'];

        $querycampi  .= "nome,";
        $queryvalori .= ":nome,";

        $querycampi  = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);

        $query = "INSERT INTO tb_marche (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql   = $this->conn->prepare($query);
        $sql->bindParam(':nome', $nome, \PDO::PARAM_STR);

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

    public function addProdotto($langs,$data)
    {
        $codice          = $data['codice'];
        $prezzo          = str_replace(',','.',$data['prezzo']);
        $prezzo_scontato = str_replace(',','.',$data['prezzo_scontato']);
        $id_marca        = (isset($data['id_marca'])) ? $data['id_marca']: '';
        $info1           = (isset($data['info1'])) ? $data['info1']: '';
        $info2           = (isset($data['info2'])) ? $data['info2']: '';
        $info3           = (isset($data['info3'])) ? $data['info3']: '';
        $info4           = (isset($data['info4'])) ? $data['info4']: '';

        $tags = '';


        if(isset($data['tags']) && is_array($data['tags']))
        {
            $tags = implode(",",$data['tags']);
        }

        $querycampi  = "";
        $queryvalori = "";

        foreach ($langs as $lang)
        {
            $querycampi .= "nome_" . $lang->sigla . ",";
            $querycampi .= "desc_" . $lang->sigla . ",";
            $querycampi .= "desc_breve_" . $lang->sigla . ",";

            $queryvalori .= ":nome_" . $lang->sigla . ",";
            $queryvalori .= ":desc_" . $lang->sigla . ",";
            $queryvalori .= ":desc_breve_" . $lang->sigla . ",";

            ${'desc_'.$lang->sigla}       = isset($data["desc_" . $lang->sigla]) ? $data["desc_" . $lang->sigla] : '';
            ${'desc_breve_'.$lang->sigla} = isset($data["desc_breve_" . $lang->sigla]) ? $data["desc_breve_" . $lang->sigla] : '';
        }

        $querycampi .= "codice,";
        $querycampi .= "prezzo,";
        $querycampi .= "prezzo_scontato,";
        $querycampi .= "id_marca,";
        $querycampi .= "info1,";
        $querycampi .= "info2,";
        $querycampi .= "info3,";
        $querycampi .= "info4,";
        $querycampi .= "tags,";

        $queryvalori .= ":codice,";
        $queryvalori .= ":prezzo,";
        $queryvalori .= ":prezzo_scontato,";
        $queryvalori .= ":id_marca,";
        $queryvalori .= ":info1,";
        $queryvalori .= ":info2,";
        $queryvalori .= ":info3,";
        $queryvalori .= ":info4,";
        $queryvalori .= ":tags,";

        $querycampi  = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);

        $query = "INSERT INTO tb_prodotti (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql   = $this->conn->prepare($query);

        $sql->bindParam(':codice', $codice, \PDO::PARAM_STR);
        $sql->bindParam(':prezzo', $prezzo, \PDO::PARAM_STR);
        $sql->bindParam(':prezzo_scontato', $prezzo_scontato, \PDO::PARAM_STR);
        $sql->bindParam(':id_marca', $id_marca, \PDO::PARAM_INT);
        $sql->bindParam(':info1', $info1, \PDO::PARAM_STR);
        $sql->bindParam(':info2', $info2, \PDO::PARAM_STR);
        $sql->bindParam(':info3', $info3, \PDO::PARAM_STR);
        $sql->bindParam(':info4', $info4, \PDO::PARAM_STR);
        $sql->bindParam(':tags', $tags, \PDO::PARAM_STR);

        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', $data["nome_" . $lang->sigla], \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', ${'desc_'.$lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', ${'desc_breve_'.$lang->sigla}, \PDO::PARAM_STR);
        }

        if ($sql->execute() === TRUE)
        {
            $id_prodotto = $this->conn->lastInsertId();

            //aggancio le categorie
            $categorieManager = new CategorieManager($this->conn);
            foreach($data['id_categorie'] as $id_cat)
            {
                $categorieManager->agganciaCategoria($id_cat,$id_prodotto,'prodotti');
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

    public function updateProdotto($langs,$data)
    {
        $id_prodotto     = $data['id'];
        $codice          = $data['codice'];
        $prezzo          = str_replace(',','.',$data['prezzo']);
        $prezzo_scontato = str_replace(',','.',$data['prezzo_scontato']);
        $id_marca        = (isset($data['id_marca'])) ? $data['id_marca'] : '';
        $info1           = (isset($data['info1'])) ? $data['info1']:'';
        $info2           = (isset($data['info2'])) ? $data['info2']:'';
        $info3           = (isset($data['info3'])) ? $data['info3']:'';
        $info4           = (isset($data['info4'])) ? $data['info4']:'';

        $tags            = '';

        if(isset($data['tags']) && is_array($data['tags']))
        {
            $tags = implode(",",$data['tags']);
        }

        $querycampivalore = '';
        foreach ($langs as $lang)
        {
            ${"nome_" . $lang->sigla} = $data['nome_' . $lang->sigla];
            ${"desc_" . $lang->sigla} = isset($data['desc_' . $lang->sigla]) ? $data['desc_'.$lang->sigla] : '';
            ${"desc_breve_" . $lang->sigla} = isset($data['desc_breve_' . $lang->sigla]) ? $data['desc_breve_'.$lang->sigla] : '';

            $querycampivalore .= "nome_" . $lang->sigla . "= :nome_" . $lang->sigla . ",";
            $querycampivalore .= "desc_" . $lang->sigla . "= :desc_" . $lang->sigla . ",";
            $querycampivalore .= "desc_breve_" . $lang->sigla . "= :desc_breve_" . $lang->sigla . ",";
        }

        $querycampivalore .= "codice=:codice,";
        $querycampivalore .= "prezzo=:prezzo,";
        $querycampivalore .= "prezzo_scontato=:prezzo_scontato,";
        $querycampivalore .= "id_marca=:id_marca,";
        $querycampivalore .= "info1=:info1,";
        $querycampivalore .= "info2=:info2,";
        $querycampivalore .= "info3=:info3,";
        $querycampivalore .= "info4=:info4,";
        $querycampivalore .= "tags=:tags,";

        $sql = $this->conn->prepare("UPDATE tb_prodotti SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
        $sql->bindParam(':id', $id_prodotto, \PDO::PARAM_INT);
        foreach ($langs as $lang)
        {
            $sql->bindParam(':nome_' . $lang->sigla . '', ${"nome_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_' . $lang->sigla . '', ${"desc_" . $lang->sigla}, \PDO::PARAM_STR);
            $sql->bindParam(':desc_breve_' . $lang->sigla . '', ${"desc_breve_" . $lang->sigla}, \PDO::PARAM_STR);

        }

        $sql->bindParam(':codice', $codice, \PDO::PARAM_STR);
        $sql->bindParam(':prezzo', $prezzo, \PDO::PARAM_STR);
        $sql->bindParam(':prezzo_scontato', $prezzo_scontato, \PDO::PARAM_STR);
        $sql->bindParam(':id_marca', $id_marca, \PDO::PARAM_INT);
        $sql->bindParam(':info1', $info1, \PDO::PARAM_STR);
        $sql->bindParam(':info2', $info2, \PDO::PARAM_STR);
        $sql->bindParam(':info3', $info3, \PDO::PARAM_STR);
        $sql->bindParam(':info4', $info4, \PDO::PARAM_STR);
        $sql->bindParam(':tags', $tags, \PDO::PARAM_STR);
        if ($sql->execute() === TRUE)
        {
            $categorieManager = new CategorieManager($this->conn);
            //prima rimuovo le categorie vecchio
            $categorieManager->rimuoviCategorie($id_prodotto,'prodotti');
            //aggancio le categorie nuove
            foreach($data['id_categorie'] as $id_cat)
            {
                $categorieManager->agganciaCategoria($id_cat,$id_prodotto,'prodotti');
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

    public function getTipoTagObject($lang,$data)
    {
        $tipoTag = new TipoTag();

        $tipoTag->setId($data['id']);
        $tipoTag->setNome($data['nome_'.$lang]);
        $tipoTag->setNomeIt($data['nome_it']);
        $tipoTag->setNomeEn($data['nome_en']);
        $tipoTag->setNomeDe($data['nome_de']);
        $tipoTag->setNomeFr($data['nome_fr']);
        $tipoTag->setNomeEs($data['nome_es']);

        return $tipoTag;
    }

    public function getTagObject($lang,$data)
    {
        $tag              = new Tag();
        $tipo_tag         = $this->getTipoTag($lang,$data['id_tipo']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie        = $categorieManager->getAgganciCategoria($lang,$data['id'],'tags');

        $tag->setId($data['id']);
        $tag->setIdTipo($data['id_tipo']);
        $tag->setTipoTag($tipo_tag);
        $tag->setNome($data['nome_'.$lang]);
        $tag->setNomeIt($data['nome_it']);
        $tag->setNomeEn($data['nome_en']);
        $tag->setNomeDe($data['nome_de']);
        $tag->setNomeFr($data['nome_fr']);
        $tag->setNomeEs($data['nome_es']);
        $tag->setDesc($data['desc_'.$lang]);
        $tag->setDescIt($data['desc_it']);
        $tag->setDescEn($data['desc_en']);
        $tag->setDescDe($data['desc_de']);
        $tag->setDescFr($data['desc_fr']);
        $tag->setDescEs($data['desc_es']);
        $tag->setCategorie($categorie);

        return $tag;
    }

    public function getMarcaObject($data)
    {
        $marca       = new Marca();
        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles('it','marche',$data['id'],2);
        $logo        = (isset($images[0]))? $images[0]:'';

        $marca->setId($data['id']);
        $marca->setNome($data['nome']);
        $marca->setLogo($logo);
        return $marca;
    }

    public function getProdottoObject($lang,$data)
    {
        $prodotto = new Prodotto();

        $fileManager = new FileManager($this->conn);
        $images      = $fileManager->getFiles($lang,'prodotti',$data['id'],1);
        $pdf         = $fileManager->getFiles($lang,'prodotti',$data['id'],3);
        $marca            = $this->getMarca($data['id_marca']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie        = $categorieManager->getAgganciCategoria($lang,$data['id'],'prodotti');

        $prodotto->setId($data['id']);
        $prodotto->setCodice($data['codice']);
        $prodotto->setNome($data['nome_'.$lang]);
        $prodotto->setNomeIt($data['nome_it']);
        $prodotto->setNomeDe($data['nome_de']);
        $prodotto->setNomeEn($data['nome_en']);
        $prodotto->setNomeFr($data['nome_fr']);
        $prodotto->setNomeEs($data['nome_es']);
        $prodotto->setDescBreve($data['desc_breve_'.$lang]);
        $prodotto->setDescBreveIt($data['desc_breve_it']);
        $prodotto->setDescBreveEn($data['desc_breve_en']);
        $prodotto->setDescBreveDe($data['desc_breve_de']);
        $prodotto->setDescBreveFr($data['desc_breve_fr']);
        $prodotto->setDescBreveEs($data['desc_breve_es']);
        $prodotto->setDesc($data['desc_'.$lang]);
        $prodotto->setDescIt($data['desc_it']);
        $prodotto->setDescEn($data['desc_en']);
        $prodotto->setDescDe($data['desc_de']);
        $prodotto->setDescFr($data['desc_fr']);
        $prodotto->setDescEs($data['desc_es']);
        $prodotto->setPrezzo($data['prezzo']);
        $prodotto->setPrezzoScontato($data['prezzo_scontato']);
        $prodotto->setInfo1($data['info1']);
        $prodotto->setInfo2($data['info2']);
        $prodotto->setInfo3($data['info3']);
        $prodotto->setInfo4($data['info4']);
        $prodotto->setStato($data['stato']);
        $prodotto->setVisibile($data['visibile']);
        $prodotto->setDataIns($data['data_ins']);

        $prodotto->setCategorie($categorie);

        if($data['tags'] != '')
        {
            $tags = [];
            $arr_tags = explode(",", $data['tags']);
            foreach ($arr_tags as $id_tag)
            {
                $tags[] = $this->getTag($lang,$id_tag);
            }
            $prodotto->setTags($tags);
        }
        if($marca)
        {
            $prodotto->setMarca($marca);
        }

        if(count($images) > 0)
        {
            $prodotto->setImages($images);
            $prodotto->setCover($images[0]);
        }

        if(count($pdf) > 0)
        {
            $prodotto->setPdf($pdf[0]);
        }

        return $prodotto;
    }

}