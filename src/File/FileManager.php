<?php

namespace File;

use File\Model\File;
use Utils\Utils;
use Utils\Log;

class FileManager
{
    const _TABLE = 'tb_file';

    /**
     * @var \PDO
     */
    protected $conn;

    protected $message;

    protected $seo_prefix = '';

    protected $filePath;

    protected $nomeFile;

    protected $estensioneFile;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // $file = $_FILE['file']
    public function upload($file, $max_size, $allowed)
    {
        //directory dove verrà uplodato il file;
        $targetDir = $_SERVER['DOCUMENT_ROOT']  . '/file/';

        //controllo che la directory esista altrimenti la creo e se non ci sono permessi di scrittura
        if (!$this->check_dir($targetDir))
        {
            $this->message = 'La directory ' . $targetDir . ' non è scrivibile';
            return false;
        }

        $fileName = $file["name"];

        $path_info = pathinfo($fileName);
        $estensione = $path_info['extension'];
        $nome = $path_info['filename'];

        //controllo che l'estensione sia consentita
        if (!$this->check_extension($estensione, $allowed))
        {
            $this->message = 'Estensione del file non è valida per ' . $fileName;
            return false;
        }

        //controllo che il file non sia più grande del consentito
        if (!$this->check_size($file["size"], $max_size))
        {
            $this->message = 'Per ' . $fileName . ' le dimensioni del file sono troppo grandi!';
            return false;
        }


        $time = time();

        //creo un nuovo nome per il file da caricare con il prefisso seo se impostato
        if ($this->seo_prefix != '')
        {
            $nuovo_nomeFile = $this->seo_prefix . "_" . $nome . "_" . $time;
        }
        else
        {
            $nuovo_nomeFile = $nome . "_" . $time;
        }

        $this->nomeFile = Utils::stringToUrl($nuovo_nomeFile);
        $this->estensioneFile = $estensione;

        //creo il path dove uplodare il file
        $this->filePath = $targetDir . DIRECTORY_SEPARATOR . $this->nomeFile . "." . $this->estensioneFile;

        $tmpname = $file['tmp_name'];

        if (move_uploaded_file($tmpname, $this->filePath))
        {
            $this->message = 'Il file ' . $this->nomeFile . ' è stato caricato con successo!';
            return true;
        }

        $this->message = 'Impossibile caricare il file ' . $fileName;
        $error = error_get_last();
        Log::warn('errore_file_manager', $error);
        return false;

    }

    public function insertInDb($modulo, $id_elem, $id_tipo, $nome)
    {
        $query = "
            INSERT INTO tb_file 
            (
                data_ins,
                modulo,
                id_elem,
                id_tipo,
                nome,
                ordine
            ) 
            VALUES 
            (
                NOW(), 
                :modulo , 
                :id_elem ,
                :id_tipo, 
                :nome ,
                0
            )
        ";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':modulo', $modulo, \PDO::PARAM_STR);
        $sql->bindParam(':id_elem', $id_elem, \PDO::PARAM_INT);
        $sql->bindParam(':id_tipo', $id_tipo, \PDO::PARAM_INT);
        $sql->bindParam(':nome', $nome, \PDO::PARAM_STR);

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this), __FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }

    }

    public function getFilesData($modulo, $id_elem, $id_tipo, $limit = false)
    {
        $files = [];
        if ($limit)
        {
            $sql = "SELECT * FROM tb_file WHERE id_elem= '$id_elem' AND modulo = '$modulo' AND id_tipo = $id_tipo ORDER BY ordine LIMIT $limit";
        }
        else
        {
            $sql = "SELECT * FROM tb_file WHERE id_elem= '$id_elem' AND modulo = '$modulo' AND id_tipo = $id_tipo ORDER BY ordine";
        }

        if ($result = $this->conn->query($sql))
        {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            {
                $files[] = $row;
            }
        }
        return $files;
    }

    public function getCount($modulo, $id_elem, $id_tipo)
    {
        $sql = "
         SELECT count(id) AS num 
         FROM tb_file 
         WHERE id_elem= '$id_elem'
         AND modulo = '$modulo'
         AND id_tipo = $id_tipo";


        if ($result = $this->conn->query($sql))
        {
            $rows = $result->fetchAll();
            return $rows[0]['num'];
        }
        return 0;
    }

    public function getFile($lang, $id)
    {
        $query = "SELECT * FROM tb_file WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id', $id, \PDO::PARAM_INT);

        if ($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                $file = $this->getFileObject($lang, $rows[0]);
                return $file;
            }
        }
        return false;
    }

    public function getFiles($lang, $modulo, $id_elem, $id_tipo = false, $limit = false)
    {
        $files = [];
        if ($limit)
        {
            $sql = "SELECT * FROM tb_file WHERE id_elem= '$id_elem' AND modulo = '$modulo' AND id_tipo = $id_tipo ORDER BY ordine LIMIT $limit";
        }
        else
        {
            $sql = "SELECT * FROM tb_file WHERE id_elem= '$id_elem' AND modulo = '$modulo' AND id_tipo = $id_tipo ORDER BY ordine";
        }

        if ($result = $this->conn->query($sql))
        {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            {
                $files[] = $this->getFileObject($lang, $row);
            }
        }
        return $files;
    }

    public function getFileObject($lang, $data)
    {
        $file = new File();
        $file->setNome($data['nome']);
        $file->setOrdine($data['ordine']);
        $file->setModulo($data['modulo']);
        $file->setId($data['id']);
        $file->setIdElem($data['id_elem']);
        $file->setIdTipo($data['id_tipo']);
        $file->setTitolo($data['titolo_' . $lang]);
        $file->setTitoloIt($data['titolo_it']);
        $file->setTitoloEn($data['titolo_en']);
        $file->setTitoloDe($data['titolo_de']);
        $file->setTitoloFr($data['titolo_fr']);
        $file->setTitoloEs($data['titolo_es']);
        $file->setDidascalia($data['didascalia_' . $lang]);
        $file->setDidascaliaIt($data['didascalia_it']);
        $file->setDidascaliaEn($data['didascalia_en']);
        $file->setDidascaliaDe($data['didascalia_de']);
        $file->setDidascaliaFr($data['didascalia_fr']);
        $file->setDidascaliaEs($data['didascalia_es']);
        $file->setDataIns($data['data_ins']);

        return $file;
    }

    public function elimina(File $file, $fileConfig)
    {
        $path_parts = pathinfo($file->nome);
        $estensione = $path_parts['extension'];
        $nome = $path_parts['filename'];

        //cancello il file principale
        unlink($_SERVER['DOCUMENT_ROOT'] . "/file/" . $nome . "." . $estensione);

        //cancello tutte le thumbs
        if(isset($fileConfig['resizes']) && count($fileConfig['resizes']) > 0)
        {
            for($i = 0; $i < count($fileConfig['resizes']); $i++)
            {
                unlink($_SERVER['DOCUMENT_ROOT'] . "/file/thumb_" . $fileConfig['resizes'][$i]['width'] ."/". $nome . "." . $estensione);
            }
        }


        //cancello il crop di default se c'è
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/file/crop/" . $nome . "." . $estensione))
        {
            unlink($_SERVER['DOCUMENT_ROOT'] . "/file/crop/" . $nome . "." . $estensione);
        }

        $sql = $this->conn->prepare("DELETE FROM tb_file WHERE id = :id");
        $sql->bindParam(':id', $file->id, \PDO::PARAM_INT);

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this), __FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }
    }

    public function modifica_file($langs, $id, $params)
    {
        $querycampivalore = "";

        foreach ($langs as $lang)
        {
            if (isset($params['didascalia_' . $lang->sigla]))
            {
                ${"didascalia_" . $lang->sigla} = trim($params['didascalia_' . $lang->sigla]);
                $querycampivalore .= "didascalia_" . $lang->sigla . "= :didascalia_" . $lang->sigla . ",";
            }
            if (isset($_POST['titolo_' . $lang->sigla]))
            {
                ${"titolo_" . $lang->sigla} = trim($params['titolo_' . $lang->sigla]);
                $querycampivalore .= "titolo_" . $lang->sigla . "= :titolo_" . $lang->sigla . ",";
            }
        }

        $sql = $this->conn->prepare("UPDATE tb_file SET " . Utils::eliminaUltimo($querycampivalore) . " WHERE id= :id ");
        $sql->bindParam(':id', $id, \PDO::PARAM_INT);

        foreach ($langs as $lang)
        {
            if (isset($params['didascalia_' . $lang->sigla]))
            {
                $sql->bindParam(':didascalia_' . $lang->sigla . '', ${"didascalia_" . $lang->sigla}, \PDO::PARAM_STR);
            }
            if (isset($_POST['titolo_' . $lang->sigla]))
            {
                $sql->bindParam(':titolo_' . $lang->sigla . '', ${"titolo_" . $lang->sigla}, \PDO::PARAM_STR);
            }
        }

        if ($sql->execute() === TRUE)
        {
            return true;
        }
        else
        {
            $sql->debugDumpParams();
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this), __FUNCTION__);
            Log::queryError($message, $sql);
            return false;
        }
    }

    public function order($pos, $modulo)
    {
        $array_pos = explode(";", $pos);

        foreach ($array_pos as $valore)
        {

            $temp = explode("=", $valore);
            $id = intval($temp[0]);
            $ordine = intval($temp[1]);

            $query = sprintf("UPDATE tb_file SET ordine = %s WHERE modulo = '%s' AND id = %s", $ordine, $modulo, $id);

            $sql = $this->conn->prepare($query);
            $sql->execute();
        }
        return;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getFilePath()
    {
        return $this->filePath;
    }

    public function getEstensioneFile()
    {
        return $this->estensioneFile;
    }

    public function getNomeFile()
    {
        return $this->nomeFile;
    }

    private function check_size($file_size, $max_size)
    {
        if ($file_size < $max_size)
        {
            return true;
        }
        return false;
    }

    private function check_extension($estensione, $allowed)
    {
        if (in_array($estensione, $allowed))
        {
            return true;
        }
        return false;
    }

    private function check_dir($dir)
    {
        if (!file_exists($dir))
        {
            mkdir($dir, 0777);  //Crea la cartella dove uploadare i file
        }
        return true;
    }


    public function setSeoPrefix($seo_prefix)
    {
        $this->seo_prefix = $seo_prefix;
        return $this;
    }
}