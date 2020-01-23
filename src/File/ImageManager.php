<?php


namespace File;

use Admin\Config;

class ImageManager
{
    protected $message;

    public function __construct()
    {

    }

    public function makeCrop($w, $h, $filePath, $quality)
    {
        //directory dove verrà uplodato il file es. /file/crop/;
        $destination_dir = $_SERVER['DOCUMENT_ROOT'] .'/file/crop/';
        $nomeFile = basename($filePath);
        $target = $destination_dir.$nomeFile;

        //controllo che la directory esista altrimenti la creo e se non ci sono permessi di scrittura
        if(!$this->check_dir($destination_dir))
        {
            $this->message = 'La directory '.$destination_dir.' non è scrivibile';
            return false;
        }

        $imgsize = getimagesize($filePath);
        $width = $imgsize[0];
        $height = $imgsize[1];
        $mime = $imgsize['mime'];

        switch ($mime)
        {
            case 'image/gif':
                $image_create = "imagecreatefromgif";
                $image = "imagegif";
                break;

            case 'image/png':
                $image_create = "imagecreatefrompng";
                $image = "imagepng";
                $quality = 7;
                break;

            case 'image/jpeg':
                $image_create = "imagecreatefromjpeg";
                $image = "imagejpeg";
                $quality = 80;
                break;

            default:
                return false;
                break;
        }

        $dst_img = imagecreatetruecolor($w, $h);
        $src_img = $image_create($filePath);

        $width_new = $height * $w / $h;
        $height_new = $width * $h / $w;
        //if the new width is greater than the actual width of the image, then the height is too large and the rest cut off, or vice versa
        if ($width_new > $width)
        {
            //cut point by height
            $h_point = (($height - $height_new) / 2);
            //copy image
            imagecopyresampled($dst_img, $src_img, 0, 0, 0, $h_point, $w, $h, $width, $height_new);
        }
        else
        {
            //cut point by width
            $w_point = (($width - $width_new) / 2);
            imagecopyresampled($dst_img, $src_img, 0, 0, $w_point, 0, $w, $h, $width_new, $height);
        }



        $image($dst_img, $target, $quality);

        if ($dst_img)
        {
            imagedestroy($dst_img);
        }

        if ($src_img)
        {
            imagedestroy($src_img);
        }

        return true;
    }

    public function makeThumb($filePath,$w,$h,$quality = 80, $fisso = 0, $latofisso = 'X')
    {
        //directory dove verrà uplodato il file es. /file/thumb_400/;
        $targetDir = $_SERVER['DOCUMENT_ROOT'] .'/file/thumb_'.$w.'/';

        //controllo che la directory esista altrimenti la creo e se non ci sono permessi di scrittura
        if(!$this->check_dir($targetDir))
        {
            $this->message = 'La directory '.$targetDir.' non è scrivibile';
            return false;
        }

        //controllo che i parametri passati siano giusti
        if(!$this->checkData($filePath,$targetDir,$w,$h,$fisso,$latofisso))
        {
            return false;
        }

        $nomeFile = basename($filePath);

        $explode = explode(".", strtolower($nomeFile));
        $key = count($explode) - 1;
        $estensione = $explode[$key];

        if ($estensione == "jpeg" || $estensione == "jpg" || $estensione == "JPG")
        {
            $handle_immagine = imagecreatefromjpeg($filePath);
        }
        elseif ($estensione == "gif")
        {
            $handle_immagine = imagecreatefromgif($filePath);
        }
        elseif ($estensione == "png")
        {
            $handle_immagine = imagecreatefrompng($filePath);
        }
        else
        {
            $this->message = "Formato immagine non valido";
            return false;
        }

        $handle_immagine_adattata = $this->adatta($handle_immagine,$estensione,$w,$h,$fisso,$latofisso);


        imagejpeg($handle_immagine_adattata, $targetDir . $nomeFile, $quality);

        chmod($targetDir . $nomeFile, 0777);

        unset($handle_immagine);
        unset($handle_immagine_adattata);

        return true;
    }

    public function getMessage()
    {
        return $this->message;
    }

    protected function adatta($handle_immagine,$estensione, $w, $h, $fisso, $latofisso)
    {
        $original_w = imagesx($handle_immagine);
        $original_h = imagesy($handle_immagine);

        if ($fisso == 1)
        {
            $new_w = $w;
            $new_h = $h;
        }
        else
        {
            if ($latofisso == "XY")
            {
                if ( ($original_w / $original_h) > ($w / $h))
                {
                    $new_w = $w;
                    $new_h = ($original_h / $original_w) * $w;
                }
                else
                {
                    $new_w = ($original_w / $original_h) * $h;
                    $new_h = $h;
                }
            }
            elseif ($latofisso == "X")
            {
                $new_w = $w;
                $new_h = ($original_h / $original_w) * $w;
            }
            elseif ($latofisso == "Y")
            {
                $new_w = ($original_w / $original_h) * $h;
                $new_h = $h;
            }
            else
            {
                if ( ($original_w / $original_h) > ($w / $h) )
                {
                    $new_w = $w;
                    $new_h = ($original_h / $original_w) * $w;
                }
                else
                {
                    $new_w = ($original_w / $original_h) * $h;
                    $new_h = $h;
                }
            }
        }
        $tmp_immagine = imagecreatetruecolor($new_w, $new_h);

        switch ($estensione)
        {
            case "png":

                imagealphablending($tmp_immagine, false);
                imagesavealpha($tmp_immagine, true);

                $transparent = imagecolorallocatealpha($tmp_immagine, 255, 255, 255, 127); //seting transparent background
                imagefilledrectangle($handle_immagine, 0, 0, $new_w, $new_h, $transparent);

                break;
            case "gif":
                // integer representation of the color black (rgb: 0,0,0)
                $background = imagecolorallocate($tmp_immagine, 0, 0, 0);

                // removing the black from the placeholder
                imagecolortransparent($tmp_immagine, $background);

                break;
        }

        imagecopyresampled($tmp_immagine, $handle_immagine, 0, 0, 0, 0, $new_w, $new_h, $original_w, $original_h);

        return $tmp_immagine;
    }

    protected function checkData($filePath,$targetDir,$w,$h,$fisso,$latofisso)
    {
        if ($filePath == "")
        {
            $this->message = "Scegliere un file da ridimensionare";
            return false;
        }
        elseif (!file_exists($filePath) || !is_file($filePath))
        {
            $this->message = "Il file selezionato non esiste";
            return false;
        }
        if (!is_numeric($w) || !is_numeric($h) || $w < 0 || $h < 0)
        {
            $this->message = "L'altezza e la larghezza dell'immagine devono essere numerici";
            return false;
        }
        if (!file_exists($targetDir) || !chmod($targetDir, 0777))
        {
            $this->message = "La cartella di destinazione non esiste o non è scrivibile";
            return false;
        }
        if ($fisso != 0 && $fisso != 1)
        {
            $this->message = "La variabile di dimensione fissa deve essere 0 o 1";
            return false;
        }
        if ($latofisso != "XY" && $latofisso != "X" && $latofisso != "Y")
        {
            $this->message = "La variabile di lato fisso devono essere X o Y o XY";
            return false;
        }

        return true;
    }

    private function check_dir($dir)
    {
        if (!file_exists($dir))
        {
            mkdir($dir, 0777);  //Crea la cartella dove uploadare i file
        }
        return true;
    }
}