<?php
namespace Db;

use PDO;
use Utils\Log;

class DbManager
{
    /**
     * @var \PDO
     */
    protected $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
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

    public function changeStato($table, $id,$stato)
    {
        $query = "UPDATE ".$table." SET stato =:stato WHERE id =:id";
        $sql   = $this->conn->prepare($query);

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

    public function deleteFilesOfElement($modulo, $id)
    {
        $sql = $this->conn->prepare("DELETE FROM tb_file WHERE id_elem = :id_elem AND modulo = :modulo");
        $sql->bindParam(':id_elem', $id, \PDO::PARAM_STR);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);
        if ($sql->execute() === TRUE)
        {
            return true;
        }
        $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__);
        Log::queryError($message,$sql);
        return false;
    }
}