<?php
namespace Log;

class Log
{
    public static function warn($nome_file, $message)
    {
        $fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/log' . "/" . $nome_file, "a+");

        if (is_array($message))
        {
            foreach ($message as $item)
            {
                fwrite($fp, $item . " \r\n");
            }
        }
        else
        {
            fwrite($fp, $message . " \r\n");
        }

        fclose($fp);
    }

    public static function queryError($message,\PDOStatement $sql)
    {
        //$message = '<br>' . date('d-m-Y').' -- '. $message . ': ' . $sql->debugDumpParams();
        $message = '<br><br>' . date('d-m-Y H:i:s').' -- '. $message . ': ';
        foreach($sql->errorInfo() as $error)
        {
            $message.= $error;
        }

        $fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/log' . "/errore_query" , "a+");
        fwrite($fp, $message . " \r\n");
        fclose($fp);
    }
}