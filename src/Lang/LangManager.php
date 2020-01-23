<?php
namespace Lang;

use Lang\Model\Lang;
use Utils\Log;

class LangManager
{
    /**
     * @var \PDO
     */
    protected $conn;

    /**
     * @var Lang
     */
    protected $lang;

    /**
     * @var array Lang
     */
    protected $allLangs = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function getSigla()
    {
        return $this->lang->sigla;
    }

    public function getIso()
    {
        return $this->lang->iso;
    }

    public function getAllLangs()
    {
        return $this->allLangs;
    }

    public function getAllAvailableLangs()
    {
        $langs = [];
        $rows = $this->getLangsData(false);

        if($rows)
        {
            foreach($rows as $row)
            {
                $lang = new Lang();
                $lang->setId($row['id']);
                $lang->setSigla($row['sigla']);
                $lang->setIso($row['iso']);
                $lang->setLabel($row['label']);
                $lang->setSiglaLong($row['sigla_long']);
                $lang->setIcona_cms($row['icona_cms']);
                $lang->setIcona_site($row['icona_site']);
                $lang->setStato($row['stato']);

                $langs[] = $lang;
            }
        }
        return $langs;
    }

    public function getDefaultLang()
    {
        return new Lang();
    }

    public function setup($lang)
    {
        $this->setAllLangs();
        $this->setLang($lang);
        $this->setLocale();
    }

    protected function setLang($sigla)
    {
        $params = $this->getLangDataBySigla($sigla);

        $this->lang = new Lang();
        $this->lang->setId($params['id']);
        $this->lang->setSigla($params['sigla']);
        $this->lang->setIso($params['iso']);
        $this->lang->setLabel($params['label']);
        $this->lang->setSiglaLong($params['sigla_long']);
        $this->lang->setIcona_cms($params['icona_cms']);
        $this->lang->setIcona_site($params['icona_site']);
        $this->lang->setStato($params['stato']);

        return;
    }

    protected function setAllLangs()
    {
        $rows = $this->getLangsData();

        if($rows)
        {
            foreach($rows as $row)
            {
                $lang = new Lang();
                $lang->setId($row['id']);
                $lang->setSigla($row['sigla']);
                $lang->setIso($row['iso']);
                $lang->setLabel($row['label']);
                $lang->setSiglaLong($row['sigla_long']);
                $lang->setIcona_cms($row['icona_cms']);
                $lang->setIcona_site($row['icona_site']);
                $lang->setStato($row['stato']);

                $this->allLangs[] = $lang;
            }
        }
    }

    protected function setLocale()
    {
        putenv("LANG=".$this->lang->iso);
        setLocale(LC_ALL,$this->lang->iso);

        $domain = $this->lang->iso;
        bindtextdomain($domain, "locale");
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);

        return;
    }

    protected function getLangDataBySigla($sigla)
    {
        $sql = $this->conn->prepare("SELECT * FROM tb_langs WHERE sigla=:sigla");
        $sql->bindParam(':sigla',$sigla,\PDO::PARAM_STR);
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
            $message = sprintf('Errore esecuzione query in %s::%s ', get_class($this),__FUNCTION__) .' '.$sql->errorInfo();
            Log::warn('errore_esecuzione_query', $message);
            return false;
        }
    }

    protected function getLangData($id_lang)
    {
        $sql = $this->conn->prepare("SELECT * FROM tb_langs WHERE id=:id");
        $sql->bindParam(':id',$id_lang,\PDO::PARAM_INT);
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
            $message = 'Errore esecuzione query in LangManager::getLangData '. $sql->errorInfo();
            Log::warn('errore_esecuzione_query', $message);
            return false;
        }
    }

    protected function getLangsData($stato = true)
    {
        if($stato)
        {
            $sql = $this->conn->prepare("SELECT * FROM tb_langs WHERE stato = 1");
        }
        else
        {
            $sql = $this->conn->prepare("SELECT * FROM tb_langs ");
        }


        if ($sql->execute())
        {
            $row = $sql->fetchAll();
            if (is_array($row) && count($row) > 0)
            {
                return $row;
            }
            return false;
        }
        else
        {
            $message = 'Errore esecuzione query in LangManager::getLangData '. $sql->errorInfo();
            Log::warn('errore_esecuzione_query', $message);
            return false;
        }
    }

    public function changeStato($id_lang,$stato)
    {
        $query = "UPDATE tb_langs SET stato =:stato WHERE id =:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':stato',$stato,\PDO::PARAM_INT);
        $sql->bindParam(':id',$id_lang,\PDO::PARAM_INT);

        if($sql->execute())
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


}