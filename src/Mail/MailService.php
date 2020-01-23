<?php

namespace Mail;

use Admin\Config;
use Utils\Log;
use PHPMailer\PHPMailer;
use PHPMailer\Exception;

class MailService
{

    protected $emailAzienda;

    protected $folder;


    public function __construct($folder)
    {
        $this->folder = $folder;
        $this->emailAzienda = (Config::IN_COSTRUZIONE) ? Config::EMAIL_DEBUG : Config::EMAIL;
    }

    public function invia_email_prova()
    {
        $params = array('nome' => 'nicola');

        //per renderizza la vista
        ob_start();
        $includeReturn = $this->getPhpMailTemplate('test', $params);
        $body = ob_get_clean();

        $email = 'nicola.tamburini@fjstudio.com';
        $subject = 'Richiesta informazioni inviata dal sito ' . Config::DOMINIO;
        $destinatari = array($this->emailAzienda, $email);

        if ($this->sendEmail($subject, $body, $destinatari, $email))
        {
            return true;
        }
        return false;
    }


    public function invia_email_contatti($data)
    {
        $params = [
            'nome' => $data['nome'],
            'messaggio' => $data['messaggio'],
            'email' => $data['email'],
            'sito' => Config::DOMINIO,
        ];
        ob_start();
        $includeReturn = $this->getPhpMailTemplate('email_contatti',$params);
        $body = ob_get_clean();
        $email = $data['email'];
        $subject = 'Richiesta informazioni inviata dal sito ' . Config::DOMINIO;
        $destinatari = array($this->emailAzienda, $email);

        if ($this->sendEmail($subject, $body, $destinatari, $email))
        {
            return true;
        }
        return false;
    }

    public function invia_email_info_prodotto($data)
    {
        $params = [
            'prodotto' => $data['prodotto'],
            'nome' => $data['nome'],
            'messaggio' => $data['messaggio'],
            'email' => $data['email'],
            'sito' => Config::DOMINIO,
        ];
        ob_start();
        $includeReturn = $this->getPhpMailTemplate('email_info_prodotto',$params);
        $body = ob_get_clean();
        $email = $data['email'];
        $subject = 'Richiesta informazioni per il prodotto ' . $data['prodotto'];
        $destinatari = array($this->emailAzienda, $email);

        if ($this->sendEmail($subject, $body, $destinatari, $email))
        {
            return true;
        }
        return false;
    }

    protected function getPhpMailTemplate($template, $params)
    {
        if ($params != null)
        {
            foreach ($params as $key => $value)
            {
                $$key = $value;
            }
        }
        include($_SERVER['DOCUMENT_ROOT'] . $this->folder . "views/email/" . $template . '.phtml');
    }

    protected function sendEmail($subject, $body, $destinatari, $email)
    {
        $mail = new PHPMailer(true);
        $mail->setFrom(Config::EMAIL, Config::DOMINIO);
        foreach ($destinatari as $dest)
        {
            $mail->addAddress($dest);
        } // $mail->addBCC('nicola.tamburini@fjstudio.com');
        $mail->addBCC('support@inyourlife.info');
        $mail->isHTML(true);
        $mail->CharSet = 'utf-8';
        $mail->addReplyTo($email);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        if (!$mail->send())
        {
            Utils::warn('errore_invio_emial', $mail->ErrorInfo);
            return false;
        }
        else
        {
            return true;
        }
    }

}
