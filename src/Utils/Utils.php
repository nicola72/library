<?php
namespace Utils;

class Utils
{
    const _ENCRIPT_METHOD = 'AES-256-CBC';
    const _SECRET_KEY = 't64td2';
    const _SECRET_IV = '6#ut4@fsawq4';

    public static function removeLastCaracter($stringa)
    {
        return substr($stringa, 0, strlen($stringa) - 1);
    }

    public static function encript($string)
    {
        $key = hash('sha256', self::_SECRET_KEY);
        $iv = substr(hash('sha256', self::_SECRET_IV), 0, 16);

        $output = openssl_encrypt($string, self::_ENCRIPT_METHOD, $key, 0, $iv);
        $output = base64_encode($output);
        return $output;
    }

    public static function decript($string)
    {
        $key = hash('sha256', self::_SECRET_KEY);
        $iv = substr(hash('sha256', self::_SECRET_IV), 0, 16);

        $output = openssl_decrypt(base64_decode($string), self::_ENCRIPT_METHOD, $key, 0, $iv);
        return $output;
    }

    public static function formatDateForDb($data='12-08-2019')
    {
        $data_array = explode("-",$data);
        $giorno = $data_array[0];
        $mese   = $data_array[1];
        $anno   = $data_array[2];

        $newdate = $anno.'-'.$mese.'-'.$giorno;
        return $newdate;
    }

    public static function formatDateForWeb($data='2019-08-31')
    {
        $data_array = explode("-",$data);
        $giorno = $data_array[2];
        $mese   = $data_array[1];
        $anno   = $data_array[0];

        $newdate = $giorno.'-'.$mese.'-'.$anno;
        return $newdate;
    }

    public static function video_url_to_embed($link , $height=null, $width=null)
    {
        $width = ($width != null) ? $width.'px' : '100%';
        $height = ($height != null) ? $height.'px' : '200px';

        $video_embed = false;

        //DAILYMOTION
        if (preg_match("#^https?://(?:www\.)?dailymotion.com#", $link))
        {

            $dailymotion = "https://www.dailymotion.com/video/";

            if (filter_var($link, FILTER_VALIDATE_URL) AND strpos($link, $dailymotion) !== FALSE) {
                $link = str_replace($dailymotion, "", $link);
                $pos_underscore = strpos($link, "_");
                $link = substr($link, 0, $pos_underscore);
            }
            $video_embed = "<iframe width='".$width."' height='".$height."' src='//www.dailymotion.com/embed/video/" . $link . "' frameborder='0' allowfullscreen></iframe>";

        }

        //VIMEO
        if (preg_match("#^https?://(?:www\.)?vimeo.com#", $link))
        {

            $vimeo = "https://vimeo.com/";

            if (filter_var($link, FILTER_VALIDATE_URL) AND strpos($link, $vimeo) !== FALSE)
            {
                $link = str_replace($vimeo, "", $link);
            }
            else if (is_numeric($link) === TRUE)
            {
                $link = $link;
            }
            else
            {
                return FALSE;
            }

            $video_embed = "<iframe width='".$width."' height='".$height."' src='//player.vimeo.com/video/" . $link . "' frameborder='0' allowfullscreen></iframe>";
        }

        //YOUTUBE
        if (preg_match("#^https?://(?:www\.)?youtube.com#", $link))
        {

            $youtube = "https://www.youtube.com/watch?v=";

            if (filter_var($link, FILTER_VALIDATE_URL) AND strpos($link, $youtube) !== FALSE)
            {
                $link = str_replace($youtube, "", $link);
            }
            $video_embed = "<iframe width='".$width."' height='".$height."' src='https://www.youtube.com/embed/" . $link . "' frameborder='0' allowfullscreen></iframe>";

        }

        return $video_embed;
    }

    public static function stringToUrl($string)
    {
        $what = array(" ", "à", "è", "é", "ì", "ò", "ù", "\\", "|", "\"", "£", "$", "&", "/", "(", ")", "=", "?", "'", "^", "€", "[", "]", "*", "+", "ç", "@", "°", "#", "§", "<", ">", ";", ",", ".", ":");
        $with = array("_", "a", "e", "e", "i", "o", "u", "-", "-", "", "", "", "e", "", "", "", "", "", "", "", "", "", "", "", "", "c", "", "", "", "", "", "", "", "", "", "");
        $string = str_replace($what, $with, $string);
        return $string;
    }

    public static function eliminaUltimo($stringa)
    {
        return substr($stringa, 0, strlen($stringa) - 1);
    }

    public static function price($value)
    {
        if ($value == '')
        {
            return "";
        }

        $value = number_format($value, 2, ",", ".");
        return "&euro; " . $value;
    }

    public static function unique_multidim_array($array, $key) // per rendere un array multidimensionale senza elementi doppione
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val)
        {
            if (!in_array($val[$key], $key_array))
            {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i ++;
        }
        return $temp_array;
    }

    public static function redirect301($url)
    {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: $url");
        die;
    }

    public static function redirect($url)
    {
        header('Location: '.$url);
        die();
    }
}