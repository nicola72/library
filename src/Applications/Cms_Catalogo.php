<?php
namespace Applications;

use Admin\Config;
use Auth\AuthManager;
use Auth\Model\User;
use Categorie\CategorieManager;
use Catalogo\CatalogoManager;
use DataTable\SSDataTable;
use Db\DbManager;
use Eventi\EventiManager;
use PDO;
use PHPMailer\Exception;
use Request\Model\Request;
use Request\RequestManager;
use Seo\SeoManager;
use Session\SessionManager;
use Session\SessionKey;
use Lang\LangManager;
use Renderer\RenderManager;
use Renderer\Model\View;
use Utils\Utils;
use FotoGallery\FotoGalleryManager;
use News\NewsManager;
use Offerte\OfferteManager;
use File\FileManager;
use File\ImageManager;
use Video\VideoManager;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class Cms_Catalogo
{
    const ROOT         = '/cms_iyl/';
    const FOLDER       = '/cms_iyl/';
    const SESSION_NAME = 'cms_iyl';
    const TB_USER      = 'tb_users_cms';
    const TB_ATTEMPTS  = 'tb_attempts_cms';
    const TB_ROLES     = 'tb_roles_cms';

    private $moduli = [
        'seo' => [
            'stato'  => true,
            'role'   => 1,
            'config' => null,
            'icon'   => 'fa fa-commenting',
            'label'  => 'seo',
        ],
        'news' => [
            'stato'  => true,
            'role'   => 2,
            'config' => 'news_config.php',
            'icon'   => 'fa fa-newspaper-o',
            'label'  => 'news',
        ],
        'eventi' => [
            'stato'  => true,
            'role'   => 2,
            'config' => 'eventi_config.php',
            'icon'   => 'fa fa-newspaper-o',
            'label'  => 'eventi',
        ],
        'offerte' => [
            'stato'  => true,
            'role'   => 2,
            'config' => 'offerte_config.php',
            'icon'   => 'fa fa-money',
            'label'  => 'offerte',
        ],
        'fotogallery' => [
            'stato'  => true,
            'role'   => 2,
            'config' => 'fotogallery_config.php',
            'icon'   => 'fa fa-file-picture-o',
            'label'  => 'fotogallery',
        ],
        'catalogo' => [
            'stato'  => true,
            'role'   => 2,
            'config' => 'catalogo_config.php',
            'icon'   => 'fa fa fa-archive',
            'label'  => 'catalogo',
        ],
    ];

    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var RequestManager
     */
    private $requestManager;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var AuthManager
     */
    private $authManager;

    /**
     * @var LangManager
     */
    private $langManager;

    /**
     * @var RenderManager
     */
    private $renderManager;

    /**
     * @var CatalogoManager
     */
    private $catalogoManager;

    /**
     * @var bool
     */
    private $is_auth;

    /**
     * @var User;
     */
    private $user;

    public $lang;
    public $controller;
    public $action;

    /**
     * @var View
     */
    protected $layout;
    protected $css_link = [];
    protected $js_link  = [];
    protected $js_view  = [];

    protected $config_modulo;

    public function __construct($conn,$autologin = false)
    {
        $this->conn   = $conn;   // connessione al db

        $this->sessionManager = new SessionManager(self::SESSION_NAME);
        $this->requestManager = new RequestManager($conn,self::ROOT);

        //in base alla url, creo la request (lang,controller,action ed eventuali parametri)
        $this->requestManager->setRequestNoDb();
        $this->request = $this->requestManager->getRequest();

        $this->authManager = new AuthManager($conn, $this->sessionManager ,self::TB_USER, self::TB_ATTEMPTS,self::TB_ROLES);
        $this->is_auth = $this->authManager->isAuth();

        //nel caso si venga dal pannello Inyourlife faccio l'autologin
        if($autologin)
        {
            $this->_login_automatico();
        }

        //se NON LOGGATO spedito alla pagina di login
        if(!$this->is_auth && $this->request->controller != 'login')
        {
            $url = $this->requestManager->getUrl('it','login');
            Utils::redirect($url);
            die();
        }

        $this->langManager = new LangManager($conn);
        $this->langManager->setup($this->request->lang);

        //setto la lingua anche nel session Manager
        $this->sessionManager->set(SessionKey::LANG,$this->request->lang);

        $this->user = json_decode($this->sessionManager->get(SessionKey::USER));
    }

    public function go()
    {
        $this->lang       = $this->request->lang;
        $this->controller = $this->request->controller;
        $this->action     = $this->request->action;

        $this->renderManager = new RenderManager(self::FOLDER, $this);
        $this->catalogoManager = new CatalogoManager($this->conn);

        if(method_exists($this, '_'.$this->controller . '_'.$this->action))
        {
            return $this->{'_'.$this->controller . '_'.$this->action}();
        }
        else
        {
            echo "Attenzione il metodo _ $this->controller _ $this->action non esiste!";
        }
        return false;
    }

    protected function _login_index()
    {
        //ESEGUO IL LOGIN
        if (isset($_POST['email']) && isset($_POST['password']))
        {
            if($this->authManager->login($_POST['email'],$_POST['password']))
            {
                $data = ['result'=> 1];
                echo json_encode($data);
                exit();
            }
            else
            {
                $messages = $this->authManager->getMessages();
                $data = ['result'=> 0, 'msg' => $messages[0]];
                echo json_encode($data);
                exit();
            }
        }

        //FACCIO VEDERE IL FORM
        $script = 'pages/scripts/js_login_form';
        $this->addJsScript($script);

        $this->setLayout('layout_login');

        $view = new View();
        echo $this->renderManager->render($view,$this->layout);
    }

    //metodo che permette di autologgarsi se proviene dal pannello Inyourlife
    protected function _login_automatico()
    {
        $ch = curl_init();
        // set URL and other appropriate options
        $admin_old = $_REQUEST['admin_old'] == 1 ? true : false; //se $admin_old è true: AMMINISTRAZIONE CON GLI SCRIPT PHP DEL CMS LOCALI(presenti in ogni dominio)

        curl_setopt($ch, CURLOPT_URL, "http://www.inyourlife.com/_ext/scripts/ajax_services.php?az=11&OP=" . $_REQUEST['OP']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // grab URL and pass it to the browser
        $ret = curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);
        //******END CURL*******

        if ($ret != -1)
        {
            //se login come amministratore o cliente
            $tipo_login = $_GET['type'];

            if ($tipo_login == 0)//login cliente
            {
                $username = Config::USERNAME;
                $password = substr(Utils::encript($username),0,8);
                if($this->authManager->login($username,$password))
                {
                    $url = $this->requestManager->getUrl('it','home');
                    Utils::redirect($url);
                    die();
                }

            }
            if ($tipo_login == 1)//login amministratore
            {
                if($this->authManager->login('support@inyourlife.info','12345678'))
                {
                    $url = $this->requestManager->getUrl('it','home');
                    Utils::redirect($url);
                    die();
                }
            }
        }
        else
        {
            header("Location: https://www.inyourlife.info");
        }
    }

    protected function _login_logout()
    {
        $this->authManager->logout();
        $url = $this->requestManager->getUrl($this->lang,'home');
        Utils::redirect($url);
        die();
    }

    protected function _home_index()
    {
        $this->setLayout();
        $view = new View();

        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_index()
    {
        $this->setLayout();

        $view = new View();
        $view->setVariable('moduli',$this->moduli);
        $view->setVariable('title_page','Moduli');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_config_generale()
    {
        $this->setLayout();

        $view = new View();
        $view->setVariable('title_page','Configurazione Generale');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_config_modulo()
    {
        $this->setLayout();

        $modulo = $this->request->getParam('modulo');

        $config_modulo = $this->getConfigModulo($modulo);
        $view = new View();
        $view->setVariable('title_page','Configurazione '.$modulo);
        $view->setVariable('config_modulo',$config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_config_lingue()
    {
        $this->setLayout();
        $langs = $this->langManager->getAllAvailableLangs();

        $view = new View();
        $view->setVariable('title_page','Configurazione Lingue');
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_change_stato_lang()
    {
        $id          = $this->request->getParam('id');
        $stato       = $this->request->getParam('stato');

        if($this->langManager->changeStato($id,$stato))
        {
            return true;
        }
        return false;
    }

    protected function _settings_logs_db()
    {
        $this->setLayout();

        try
        {
            $file_log = @file_get_contents($_SERVER['DOCUMENT_ROOT'].'/log/errore_query');
            $view = new View();
            $view->setVariable('title_page','Logs DB');
            $view->setVariable('file_log',$file_log);

            echo $this->renderManager->render($view,$this->layout);

        }
        catch(\Exception $e)
        {
            $this->sessionManager->addErrorMessage('Il file non esiste '. $e->getMessage());
            $url = $this->requestManager->getUrl($this->lang,'settings','logs_db');
            Utils::redirect($url);
            exit();
        }
    }

    protected function _settings_elimina_log_db()
    {
        unlink($_SERVER['DOCUMENT_ROOT'].'/log/errore_query');
        $this->sessionManager->addSuccessMessage('File eliminato con successo!');
        $url = $this->requestManager->getUrl($this->lang,'settings','logs_db');
        Utils::redirect($url);
        exit();
    }

    protected function _settings_utenti()
    {
        $this->setLayout();

        $utenti = $this->authManager->getUsers();

        $view = new View();
        $view->setVariable('title_page','Utenti CMS');
        $view->setVariable('utenti',$utenti);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _settings_elimina_utente()
    {
        $id = $this->request->getParam('id');

        if($this->authManager->deleteUser($id))
        {
            $this->sessionManager->addSuccessMessage('Utente eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare utente');
        }

        $url = $this->requestManager->getUrl($this->lang, 'settings', 'utenti');
        Utils::redirect($url);
        exit();
    }

    //per inserire autologin cliente da pannello inyourlife
    protected function _settings_aggiungi_accesso_cliente()
    {
        $username = Config::USERNAME;
        $password = substr(Utils::encript($username),0,8);
        $id_ruolo = 2;

        $form = 'form_aggiungi_utente';
        $form_action = $this->getUrl($this->lang,'settings','aggiungi_utente');

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('username',$username);
        $view->setVariable('password',$password);
        $view->setVariable('id_ruolo',$id_ruolo);
        $view->setVariable('per_accesso_diretto',true);
        $view->setTemplate('pages/modals/settings_add_utente');
        echo $this->renderManager->render($view);
    }

    protected function _settings_aggiungi_utente()
    {
        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['username']) || empty($data['password']) || empty($data['id_ruolo']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                echo json_encode($result);
                exit();
            }
            //fine controllo


            //controllo che non sia già stato inserito con questo username
            if($this->authManager->getUserId($data['username']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Esiste già un utente con questo username.'];
                echo json_encode($result);
                exit();
            }

            //faccio l'inserimento
            if($this->authManager->addUser($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo utente');
                $url = $this->requestManager->getUrl($this->lang,'settings','utenti');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo utente'];
            }

            echo json_encode($result);
            exit();
        }

        $form = 'form_aggiungi_utente';
        $form_action = $this->getUrl($this->lang,'settings','aggiungi_utente');

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('per_accesso_diretto',false);
        $view->setTemplate('pages/modals/settings_add_utente');
        echo $this->renderManager->render($view);
    }

    protected function _settings_crea_zip()
    {
        // Get real path for our folder
        $rootPath = realpath($_SERVER['DOCUMENT_ROOT']);
        $file_name = $_SERVER['DOCUMENT_ROOT'].'/cms.zip';
        unlink($file_name);

        // Initialize archive object
        $zip = new ZipArchive();
        $zip->open($file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::LEAVES_ONLY);

        try
        {
            foreach ($files as $name => $file)
            {
                // Skip directories (they would be added automatically)
                if (!$file->isDir())
                {
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);
                }
            }

            // Zip archive will be created only after closing object
            $zip->close();

            $this->sessionManager->addSuccessMessage('Successo! Il file cms.zip è stato creato');
        }
        catch(Exception $e)
        {
            $this->sessionManager->addErrorMessage('Errore! Qualcosa è andato storto');
        }

        $url = $this->getUrl($this->lang,'settings');
        Utils::redirect($url);
        exit();
    }

    protected function _settings_info_installazione()
    {
        $this->setLayout();

        $title_page = "Come installare il CMS";

        $view = new View();
        $view->setVariable('title_page',$title_page);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _seo_index()
    {
        $this->checkPrivileges($this->moduli['seo']['role']);

        $requests = $this->requestManager->getRequestsInDb();
        $this->js_link[] = 'js/seo.js';
        $this->setLayout();

        $view = new View();
        $view->setVariable('requests',$requests);

        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _seo_keywords()
    {
        if(!empty($this->request->paramsPost))
        {
            $data     = $this->request->paramsPost;
            $lang     = $data['lang'];
            $keywords = $data['keywords'];

            $requests   = $this->requestManager->getRequestsInDb($lang);
            $seoManager = new SeoManager($this->conn,$this->request);

            if($seoManager->updateSeoKeywords($requests,$keywords))
            {
                $this->sessionManager->addSuccessMessage('Successo! Keywords aggiornate.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore!'];
            }

            echo json_encode($result);
            exit();
        }
        $langs = $this->langManager->getAllLangs();
        $view = new View();
        $view->setTemplate('pages/modals/seo_add_keywords');
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view);

    }

    protected function _seo_alias()
    {
        $alias = $this->requestManager->getAlias(false);

        $this->setLayout();

        $view = new View();
        $view->setVariable('alias',$alias);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _seo_aggiungi_alias()
    {
        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;
            if($this->requestManager->addAlias($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Dominio inserito.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiunge il dominio'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-alias';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','aggiungi_alias');

        $view = new View();
        $view->setTemplate('pages/modals/seo_add_alias');
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        echo $this->renderManager->render($view);
    }

    protected function _seo_modifica_alias()
    {
        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;
            if($this->requestManager->updateAlias($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Dominio inserito.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiunge il dominio'];
            }

            echo json_encode($result);
            exit();
        }

        $id_alias = $this->request->getParam('id');
        $alias    = $this->requestManager->getAliasOne($id_alias);

        $form        = 'form-modifica-alias';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','modifica_alias');

        $view = new View();
        $view->setTemplate('pages/modals/seo_update_alias');
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('alias',$alias);
        echo $this->renderManager->render($view);
    }

    protected function _seo_elimina_alias()
    {
        $id_alias = $this->request->getParam('id');

        if($this->requestManager->removeAlias($id_alias))
        {
            $this->sessionManager->addSuccessMessage('Successo! Dominio eliminato.');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il dominio.');
        }

        $url = $this->requestManager->getUrl($this->lang,'seo','alias');
        Utils::redirect($url);
        exit();
    }

    protected function _seo_modifica_request()
    {

        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;
            if($this->requestManager->updateRequest($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! URL aggiornata.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare la request'];
            }

            echo json_encode($result);
            exit();
        }

        $id_request   = $this->request->getParam('id');
        $req_tochange = $this->requestManager->getRequestById($id_request);

        $alias       = $this->requestManager->getAlias();
        $langs       = $this->langManager->getAllLangs();
        $form        = 'form-update-request';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','modifica_request');

        $view = new View();
        $view->setTemplate('pages/modals/seo_update_request');
        $view->setVariable('alias', $alias);
        $view->setVariable('langs', $langs);
        $view->setVariable('req_tochange', $req_tochange);
        $view->setVariable('form', $form);
        $view->setVariable('form_action', $form_action);
        echo $this->renderManager->render($view);
    }

    protected function _seo_aggiungi_request()
    {
        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;
            if($this->requestManager->addRequest($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Route inserita.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiunge la route'];
            }

            echo json_encode($result);
            exit();
        }

        //altrimenti faccio vedere il form d'inserimento

        $alias       = $this->requestManager->getAlias();
        $langs       = $this->langManager->getAllLangs();
        $form        = 'form-aggiungi-route';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','aggiungi_request');

        $view = new View();
        $view->setTemplate('pages/modals/seo_add_request');
        $view->setVariable('alias',$alias);
        $view->setVariable('langs',$langs);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        echo $this->renderManager->render($view);
    }

    protected function _seo_elimina_request()
    {
        $id_request = $this->request->getParam('id');

        if($this->requestManager->removeRequest($id_request))
        {
            $seoManager = new SeoManager($this->conn,$this->request);
            //rimuovo anche il seo se presente
            $seoManager->removeItemByIdRequest($id_request);
            $this->sessionManager->addSuccessMessage('Request eliminata con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il record.');
        }

        $url = $this->requestManager->getUrl($this->lang,'seo','index');
        Utils::redirect($url);
        exit();
    }

    protected function _seo_aggiungi_seo()
    {
        $seoManager     = new SeoManager($this->conn,$this->request);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;
            if($seoManager->addSeo($data))
            {
                //aggiorno la prop with_seo della route
                $this->requestManager->updateNoIndex($data['id_request'],0);
                $this->sessionManager->addSuccessMessage('Successo! Seo inserito.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile inserire il seo'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il form di inserimento
        $id_request = $this->request->getParam('id');

        $form = 'form-aggiungi-seo';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','aggiungi_seo');

        $view = new View();
        $view->setTemplate('pages/modals/seo_add_seo');
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('id_request',$id_request);
        echo $this->renderManager->render($view);
    }

    protected function _seo_elimina_seo()
    {
        $id_seo = $this->request->getParam('id');

        $seoManager = new SeoManager($this->conn,$this->request);
        $seo_item = $seoManager->getSeoItem($id_seo);

        if($seo_item)
        {
            if($seoManager->removeItem($id_seo))
            {
                //imposto la prop with_seo della route a zero
                $this->requestManager->updateNoIndex($seo_item->id_request,1);
                $this->sessionManager->addSuccessMessage('Seo eliminato con successo!');
            }
            else
            {
                $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il record.');
            }
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il record, seo non trovato.');
        }

        $url = $this->requestManager->getUrl($this->lang,'seo','index');
        Utils::redirect($url);
        exit();
    }

    protected function _seo_modifica_seo()
    {
        $seoManager = new SeoManager($this->conn,$this->request);

        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if($seoManager->updateSeo($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Seo aggiornato.');
                $result = ['result' => 1];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare il seo'];
            }

            echo json_encode($result);
            exit();
        }

        $id_seo = $this->request->getParam('id');
        $seo    = $seoManager->getSeoItem($id_seo);

        $form = 'form-update-seo';
        $form_action = $this->requestManager->getUrl($this->lang,'seo','modifica_seo');
        $back_url    = $this->requestManager->getUrl($this->lang,'seo');

        $view = new View();
        $view->setTemplate('pages/modals/seo_update_seo');

        $view->setVariable('seo',$seo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('back_url',$back_url);
        echo $this->renderManager->render($view);
    }

    protected function _seo_list()
    {
        $this->setLayout();

        $seoManager = new SeoManager($this->conn,$this->request);
        $seo_list   = $seoManager->getSeoList();

        $view = new View();
        $view->setVariable('seo_list',$seo_list);
        $view->setVariable('title_page','Lista Seo');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _fotogallery_index()
    {
        $this->js_link[] = 'js/fotogallery.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        $fotoGalleryManager = new FotoGalleryManager($this->conn);
        $fotogalleries = $fotoGalleryManager->getFotoGalleries($this->lang);

        $categorie_agganciate = [];
        if(count($fotogalleries) > 0)
        {
            $categorieManager = new CategorieManager($this->conn);
            foreach ($fotogalleries as $item)
            {
                $agganci = $categorieManager->getAgganciCategoria($this->lang,$item->id,'fotogallery');
                $categorie_agganciate[$item->id] = $agganci;
            }
        }

        $view = new View();
        $view->setVariable('fotogalleries',$fotogalleries);
        $view->setVariable('categorie_agganciate',$categorie_agganciate);
        $view->setVariable('title_page','fotogallery');
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _fotogallery_aggiungi()
    {
        $this->setConfigModulo($this->moduli['fotogallery']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'fotogallery');
        $fotoGalleryManager = new FotoGalleryManager($this->conn);
        $langs = $this->langManager->getAllLangs();


        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {

            $data = $this->request->paramsPost;

            if($this->config_modulo['con_categorie'])
            {

                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($fotoGalleryManager->addFotoGallery($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'fotogallery');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }


        //faccio vedere il modal col form
        $form = 'form-aggiungi-fotogallery';
        $form_action = $this->requestManager->getUrl($this->lang,'fotogallery','aggiungi');

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setTemplate('pages/modals/fotogallery_add');
        echo $this->renderManager->render($view);
    }

    protected function _fotogallery_modifica()
    {
        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        $langs              = $this->langManager->getAllLangs();
        $fotoGalleryManager = new FotoGalleryManager($this->conn);
        $categorieManager   = new CategorieManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if($this->config_modulo['con_categorie'])
            {
                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }
            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($fotoGalleryManager->updateFotoGallery($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'fotogallery','modifica',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_fotogallery_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'fotogallery');
            Utils::redirect($url);
            exit();
        }
        $item        = $fotoGalleryManager->getFotoGallery($this->lang,$id);
        $categorie   = $categorieManager->getCategorie($this->lang,'fotogallery');
        $form        = 'form-modifica-fotogallery';
        $form_action = $this->requestManager->getUrl($this->lang,'fotogallery','modifica');
        $cm          = $this->config_modulo;
        $title_page  = 'Modifica '.$item->nome;

        $view = new View();
        $view->setVariable('item',$item);
        $view->setVariable('categorie', $categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$cm);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _fotogallery_elimina()
    {
        $id = $this->request->getParam('id');

        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        $fotoGalleryManager = new FotoGalleryManager($this->conn);
        $categoriaManager   = new CategorieManager($this->conn);
        $fileManager        = new FileManager($this->conn);
        $dbManager          = new DbManager($this->conn);

        if($dbManager->changeStato('tb_fotogallery',$id,0))
        {
            $files = $fileManager->getFiles($this->lang,'fotogallery',$id,1);
            if(count($files) > 0)
            {
                foreach($files as $file)
                {
                    $fileManager->elimina($file,$this->config_modulo['upload_config_1']);
                }
            }
            //elimino tutti i record nella tabella tb_file
            $dbManager->deleteFilesOfElement('fotogallery',$id);
            $categoriaManager->rimuoviCategorie($id,'fotogallery');
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'fotogallery');
        Utils::redirect($url);
        exit();
    }

    protected function _fotogallery_categorie()
    {
        $this->js_link[] = 'js/fotogallery.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['fotogallery']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'fotogallery');

        $nomi_genitore = [];
        if(count($categorie) > 0)
        {
            foreach($categorie as $cat)
            {
                $id_genitore = $cat->id_genitore;
                if($id_genitore != 0)
                {
                    $genitore = $categorieManager->getCategoria($this->lang,$id_genitore);
                    $nomi_genitore[$cat->id] = $genitore->nome;
                }
                else
                {
                    $nomi_genitore[$cat->id] = 'Nessuno';
                }

            }
        }

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('nomi_genitore',$nomi_genitore);
        $view->setVariable('title_page','categorie gallery');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _fotogallery_aggiungi_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($categorieManager->addCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'fotogallery','categorie');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'fotogallery','aggiungi_categoria');
        $categorie   = $categorieManager->getCategorie($this->lang,'fotogallery');

        $cm = $this->config_modulo;

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('modulo','fotogallery');
        $view->setVariable('langs',$langs);
        $view->setVariable('lang',$this->lang);
        $view->setVariable('cm',$cm);
        $view->setVariable('categorie',$categorie);
        $view->setTemplate('pages/modals/categorie_add');
        echo $this->renderManager->render($view);
    }

    protected function _fotogallery_elimina_categoria()
    {
        $id = $this->request->getParam('id');
        $categorieManager = new CategorieManager($this->conn);
        $dbManager = new DbManager($this->conn);

        //controllo che non ci siano elementi attive associata a questa categoria
        if($categorieManager->checkHaveAgganci($id))
        {
            $this->sessionManager->addErrorMessage('Errore! Questa categoria non può essere eliminata...sono presenti elementi associate ad essa');
            $url = $this->requestManager->getUrl($this->lang,'fotogallery','categorie');
            Utils::redirect($url);
            exit();
        }

        if($dbManager->changeStato('tb_categorie',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'fotogallery','categorie');
        Utils::redirect($url);
        exit();
    }

    protected function _fotogallery_modifica_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($categorieManager->updateCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'fotogallery','modifica_categoria',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_categorie_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'fotogallery','categorie');
            Utils::redirect($url);
            exit();
        }
        $categoria       = $categorieManager->getCategoria($this->request->lang,$id);

        $altre_categorie = $categorieManager->getAltreCategorie($categoria->id,$this->lang,'fotogallery');

        $form = 'form-modifica-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'fotogallery','modifica_categoria');

        $title_page = 'Modifica Categoria '.$categoria->nome;

        $view = new View();
        $view->setVariable('categoria',$categoria);
        $view->setVariable('altre_categorie', $altre_categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','fotogallery');
        $view->setTemplate('pages/categorie_modifica');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _fotogallery_change_visibility()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_fotogallery';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _fotogallery_immagini()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['fotogallery']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'fotogallery');
            Utils::redirect($url);
            die;
        }

        $fotogalleryManager = new FotoGalleryManager($this->conn);
        $item = $fotogalleryManager->getFotoGallery($this->lang,$id_elem);
        $id_tipo = 1;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'fotogallery';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_index()
    {
        $this->js_link[] = 'js/news.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['news']['config']);

        $newsManager = new NewsManager($this->conn);
        $news = $newsManager->getNews($this->lang);

        $categorie_agganciate = [];
        if(count($news) > 0)
        {
            $categorieManager = new CategorieManager($this->conn);
            foreach ($news as $item)
            {
                $agganci = $categorieManager->getAgganciCategoria($this->lang,$item->id,'news');
                $categorie_agganciate[$item->id] = $agganci;
            }
        }

        $view = new View();
        $view->setVariable('news',$news);
        $view->setVariable('categorie_agganciate',$categorie_agganciate);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_aggiungi()
    {
        $this->setConfigModulo($this->moduli['news']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'news');
        $newsManager = new NewsManager($this->conn);
        $langs = $this->langManager->getAllLangs();


        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {

            $data = $this->request->paramsPost;

            if($this->config_modulo['con_categorie'])
            {

                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($newsManager->addNews($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'news');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }


        //faccio vedere il modal col form
        $form = 'form-aggiungi-news';
        $form_action = $this->requestManager->getUrl($this->lang,'news','aggiungi');

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setTemplate('pages/modals/news_add');
        echo $this->renderManager->render($view);
    }

    protected function _news_categorie()
    {
        $this->js_link[] = 'js/news.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['news']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'news');

        $nomi_genitore = [];
        if(count($categorie) > 0)
        {
            foreach($categorie as $cat)
            {
                $id_genitore = $cat->id_genitore;
                if($id_genitore != 0)
                {
                    $genitore = $categorieManager->getCategoria($this->lang,$id_genitore);
                    $nomi_genitore[$cat->id] = $genitore->nome;
                }
                else
                {
                    $nomi_genitore[$cat->id] = 'Nessuno';
                }

            }
        }

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('nomi_genitore',$nomi_genitore);
        $view->setVariable('title_page','categorie news');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_modifica()
    {
        $this->setConfigModulo($this->moduli['news']['config']);

        $langs              = $this->langManager->getAllLangs();
        $newsManager        = new NewsManager($this->conn);
        $categorieManager   = new CategorieManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if($this->config_modulo['con_categorie'])
            {
                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }
            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($newsManager->updateNews($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'news','modifica',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_news_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news');
            Utils::redirect($url);
            exit();
        }
        $item        = $newsManager->getNewsItem($this->lang,$id);
        $categorie   = $categorieManager->getCategorie($this->lang,'news');
        $form        = 'form-modifica-news';
        $form_action = $this->requestManager->getUrl($this->lang,'news','modifica');
        $cm          = $this->config_modulo;
        $title_page  = 'Modifica '.$item->nome;

        $view = new View();
        $view->setVariable('item',$item);
        $view->setVariable('categorie', $categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$cm);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_elimina()
    {
        $id = $this->request->getParam('id');

        $this->setConfigModulo($this->moduli['news']['config']);

        $categoriaManager   = new CategorieManager($this->conn);
        $fileManager        = new FileManager($this->conn);
        $dbManager          = new DbManager($this->conn);

        if($dbManager->changeStato('tb_news',$id,0))
        {
            $files = $fileManager->getFiles($this->lang,'news',$id,1);
            if(count($files) > 0)
            {
                foreach($files as $file)
                {
                    $fileManager->elimina($file,$this->config_modulo['upload_config_1']);
                }
            }
            //elimino tutti i record nella tabella tb_file
            $dbManager->deleteFilesOfElement('news',$id);
            $categoriaManager->rimuoviCategorie($id,'news');
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'news');
        Utils::redirect($url);
        exit();
    }

    protected function _news_immagini()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['news']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news');
            Utils::redirect($url);
            die;
        }

        $newsManager = new NewsManager($this->conn);
        $item = $newsManager->getNewsItem($this->lang,$id_elem);
        $id_tipo = 1;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'news';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_file()
    {
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['news']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news');
            Utils::redirect($url);
            die;
        }

        $newsManager = new NewsManager($this->conn);
        $item = $newsManager->getNewsItem($this->lang,$id_elem);
        $id_tipo = 2;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'news';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $file_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','file');

        $view = new View();
        $view->setVariable('title_page','Files per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('file_presenti',$file_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _news_video()
    {
        $this->setConfigModulo($this->moduli['news']['config']);

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news');
            Utils::redirect($url);
            die;
        }

        $newsManager = new NewsManager($this->conn);
        $item = $newsManager->getNewsItem($this->lang,$id_elem);
        $langs = $this->langManager->getAllLangs();
        $modulo = 'news';

        $videoManager = new VideoManager($this->conn);
        $videos = $videoManager->getVideos($this->lang,$modulo,$id_elem);

        $view = new View();
        $view->setVariable('title_page','Video per '.$item->nome);
        $view->setVariable('videos', $videos);
        $view->setVariable('item', $item);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _news_aggiungi_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['news']['config']);

        $modulo = 'news';

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il link è obbligatorio'];
                echo json_encode($result);
                exit();
            }
            //fine controllo

            $videoManager = new VideoManager($this->conn);
            //faccio l'inserimento
            if($videoManager->addVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'news','video',['id'=>$data['id_elem']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $id_elem = $this->request->getParam('id');
        $form = 'form-aggiungi-video';
        $form_action = $this->requestManager->getUrl($this->lang,'news','aggiungi_video');

        $view = new View();
        $view->setTemplate('pages/modals/video_add');
        $view->setVariable('id_elem',$id_elem);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view);
    }

    protected function _news_modifica_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['news']['config']);
        $videoManager = new VideoManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                echo json_encode($result);
                exit();
            }
            //fine controllo


            //faccio l'aggiornamento
            if($videoManager->updateVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'news','modifica_video',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_video_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news','video');
            Utils::redirect($url);
            exit();
        }
        $video = $videoManager->getVideo($this->request->lang,$id);

        $form = 'form-modifica-video';
        $form_action = $this->requestManager->getUrl($this->lang,'news','modifica_video');

        $title_page = 'Modifica Video '.$video->link;
        $id_news = $this->request->getParam('id');

        $view = new View();
        $view->setVariable('video',$video);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','news');
        $view->setVariable('id_news',$id_news);
        $view->setTemplate('pages/video_modifica');
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _news_change_visibility()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_news';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _news_aggiungi_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['news']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($categorieManager->addCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'news','categorie');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'news','aggiungi_categoria');
        $categorie   = $categorieManager->getCategorie($this->lang,'news');

        $cm = $this->config_modulo;

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('modulo','news');
        $view->setVariable('langs',$langs);
        $view->setVariable('lang',$this->lang);
        $view->setVariable('cm',$cm);
        $view->setVariable('categorie',$categorie);
        $view->setTemplate('pages/modals/categorie_add');
        echo $this->renderManager->render($view);
    }

    protected function _news_elimina_categoria()
    {
        $id = $this->request->getParam('id');
        $categorieManager = new CategorieManager($this->conn);
        $dbManager = new DbManager($this->conn);

        //controllo che non ci siano elementi attive associata a questa categoria
        if($categorieManager->checkHaveAgganci($id))
        {
            $this->sessionManager->addErrorMessage('Errore! Questa categoria non può essere eliminata...sono presenti elementi associate ad essa');
            $url = $this->requestManager->getUrl($this->lang,'news','categorie');
            Utils::redirect($url);
            exit();
        }

        if($dbManager->changeStato('tb_categorie',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'news','categorie');
        Utils::redirect($url);
        exit();
    }

    protected function _news_modifica_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['news']['config']);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($categorieManager->updateCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'news','modifica_categoria',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_categorie_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'news','categorie');
            Utils::redirect($url);
            exit();
        }
        $categoria       = $categorieManager->getCategoria($this->request->lang,$id);

        $altre_categorie = $categorieManager->getAltreCategorie($categoria->id,$this->lang,'news');

        $form = 'form-modifica-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'news','modifica_categoria');

        $title_page = 'Modifica Categoria '.$categoria->nome;

        $view = new View();
        $view->setVariable('categoria',$categoria);
        $view->setVariable('altre_categorie', $altre_categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','news');
        $view->setTemplate('pages/categorie_modifica');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_index()
    {
        $this->js_link[] = 'js/offerte.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['offerte']['config']);

        $offerteManager = new OfferteManager($this->conn);
        $offerte = $offerteManager->getOfferte($this->lang);

        $categorie_agganciate = [];
        if(count($offerte) > 0)
        {
            $categorieManager = new CategorieManager($this->conn);
            foreach ($offerte as $item)
            {
                $agganci = $categorieManager->getAgganciCategoria($this->lang,$item->id,'offerte');
                $categorie_agganciate[$item->id] = $agganci;
            }
        }

        $view = new View();
        $view->setVariable('offerte',$offerte);
        $view->setVariable('categorie_agganciate',$categorie_agganciate);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_aggiungi()
    {
        $this->setConfigModulo($this->moduli['offerte']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'offerte');

        $offerteManager = new OfferteManager($this->conn);
        $langs = $this->langManager->getAllLangs();


        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {

            $data = $this->request->paramsPost;

            if($this->config_modulo['con_categorie'])
            {

                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($offerteManager->addOfferta($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'offerte');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }


        //faccio vedere il modal col form
        $form = 'form-aggiungi-offerte';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','aggiungi');

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setTemplate('pages/modals/offerte_add');
        echo $this->renderManager->render($view);
    }

    protected function _offerte_categorie()
    {
        $this->js_link[] = 'js/offerte.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['offerte']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'offerte');


        $nomi_genitore = [];
        if(count($categorie) > 0)
        {
            foreach($categorie as $cat)
            {
                $id_genitore = $cat->id_genitore;
                if($id_genitore != 0)
                {
                    $genitore = $categorieManager->getCategoria($this->lang,$id_genitore);
                    $nomi_genitore[$cat->id] = $genitore->nome;
                }
                else
                {
                    $nomi_genitore[$cat->id] = 'Nessuno';
                }

            }
        }

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('nomi_genitore',$nomi_genitore);
        $view->setVariable('title_page','categorie offerte');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_modifica()
    {
        $this->setConfigModulo($this->moduli['offerte']['config']);

        $langs            = $this->langManager->getAllLangs();
        $offerteManager   = new OfferteManager($this->conn);
        $categorieManager = new CategorieManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if($this->config_modulo['con_categorie'])
            {
                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }
            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($offerteManager->updateOfferta($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'offerte','modifica',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_offerte_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte');
            Utils::redirect($url);
            exit();
        }
        $item        = $offerteManager->getOfferta($this->lang,$id);
        $categorie   = $categorieManager->getCategorie($this->lang,'offerte');
        $form        = 'form-modifica-offerte';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','modifica');
        $cm          = $this->config_modulo;
        $title_page  = 'Modifica '.$item->nome;

        $view = new View();
        $view->setVariable('item',$item);
        $view->setVariable('categorie', $categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$cm);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_elimina()
    {
        $id = $this->request->getParam('id');

        $this->setConfigModulo($this->moduli['offerte']['config']);

        $categoriaManager   = new CategorieManager($this->conn);
        $fileManager        = new FileManager($this->conn);
        $dbManager          = new DbManager($this->conn);

        if($dbManager->changeStato('tb_offerte',$id,0))
        {
            $files = $fileManager->getFiles($this->lang,'offerte',$id,1);
            if(count($files) > 0)
            {
                foreach($files as $file)
                {
                    $fileManager->elimina($file,$this->config_modulo['upload_config_1']);
                }
            }
            //elimino tutti i record nella tabella tb_file
            $dbManager->deleteFilesOfElement('offerte',$id);
            $categoriaManager->rimuoviCategorie($id,'offerte');
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'offerte');
        Utils::redirect($url);
        exit();
    }

    protected function _offerte_immagini()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['offerte']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte');
            Utils::redirect($url);
            die;
        }

        $offerteManager = new OfferteManager($this->conn);
        $item = $offerteManager->getOfferta($this->lang,$id_elem);
        $id_tipo = 1;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'offerte';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_file()
    {
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['offerte']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte');
            Utils::redirect($url);
            die;
        }

        $offerteManager = new OfferteManager($this->conn);
        $item = $offerteManager->getOfferta($this->lang,$id_elem);
        $id_tipo = 2;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'offerte';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $file_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','file');

        $view = new View();
        $view->setVariable('title_page','Files per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('file_presenti',$file_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _offerte_video()
    {
        $this->setConfigModulo($this->moduli['offerte']['config']);

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte');
            Utils::redirect($url);
            die;
        }

        $offerteManager = new OfferteManager($this->conn);
        $item = $offerteManager->getOfferta($this->lang,$id_elem);
        $langs = $this->langManager->getAllLangs();
        $modulo = 'offerte';

        $videoManager = new VideoManager($this->conn);
        $videos = $videoManager->getVideos($this->lang,$modulo,$id_elem);

        $view = new View();
        $view->setVariable('title_page','Video per '.$item->nome);
        $view->setVariable('videos', $videos);
        $view->setVariable('item', $item);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _offerte_aggiungi_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['offerte']['config']);

        $modulo = 'offerte';

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il link è obbligatorio'];
                echo json_encode($result);
                exit();
            }
            //fine controllo

            $videoManager = new VideoManager($this->conn);
            //faccio l'inserimento
            if($videoManager->addVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'offerte','video',['id'=>$data['id_elem']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $id_elem = $this->request->getParam('id');
        $form = 'form-aggiungi-video';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','aggiungi_video');

        $view = new View();
        $view->setTemplate('pages/modals/video_add');
        $view->setVariable('id_elem',$id_elem);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view);
    }

    protected function _offerte_modifica_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['offerte']['config']);
        $videoManager = new VideoManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                echo json_encode($result);
                exit();
            }
            //fine controllo


            //faccio l'aggiornamento
            if($videoManager->updateVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'offerte','modifica_video',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_video_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte','video');
            Utils::redirect($url);
            exit();
        }
        $video = $videoManager->getVideo($this->request->lang,$id);

        $form = 'form-modifica-video';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','modifica_video');

        $title_page = 'Modifica Video '.$video->link;
        $id_news = $this->request->getParam('id');

        $view = new View();
        $view->setVariable('video',$video);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','offerte');
        $view->setVariable('id_news',$id_news);
        $view->setTemplate('pages/video_modifica');
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _offerte_change_visibility()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_offerte';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _offerte_aggiungi_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['offerte']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($categorieManager->addCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'offerte','categorie');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','aggiungi_categoria');
        $categorie   = $categorieManager->getCategorie($this->lang,'offerte');

        $cm = $this->config_modulo;

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('modulo','offerte');
        $view->setVariable('langs',$langs);
        $view->setVariable('lang',$this->lang);
        $view->setVariable('cm',$cm);
        $view->setVariable('categorie',$categorie);
        $view->setTemplate('pages/modals/categorie_add');
        echo $this->renderManager->render($view);
    }

    protected function _offerte_elimina_categoria()
    {
        $id = $this->request->getParam('id');
        $categorieManager = new CategorieManager($this->conn);
        $dbManager = new DbManager($this->conn);

        //controllo che non ci siano elementi attive associata a questa categoria
        if($categorieManager->checkHaveAgganci($id))
        {
            $this->sessionManager->addErrorMessage('Errore! Questa categoria non può essere eliminata...sono presenti elementi associate ad essa');
            $url = $this->requestManager->getUrl($this->lang,'offerte','categorie');
            Utils::redirect($url);
            exit();
        }

        if($dbManager->changeStato('tb_categorie',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'offerte','categorie');
        Utils::redirect($url);
        exit();
    }

    protected function _offerte_modifica_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['offerte']['config']);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($categorieManager->updateCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'offerte','modifica_categoria',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_categorie_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'offerte','categorie');
            Utils::redirect($url);
            exit();
        }
        $categoria       = $categorieManager->getCategoria($this->request->lang,$id);
        $altre_categorie = $categorieManager->getAltreCategorie($categoria->id,$this->lang,'offerte');

        $form = 'form-modifica-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'offerte','modifica_categoria');

        $title_page = 'Modifica Categoria '.$categoria->nome;

        $view = new View();
        $view->setVariable('categoria',$categoria);
        $view->setVariable('altre_categorie', $altre_categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','offerte');
        $view->setTemplate('pages/categorie_modifica');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_index()
    {
        $this->js_link[] = 'js/eventi.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['eventi']['config']);

        $eventiManager = new EventiManager($this->conn);
        $eventi = $eventiManager->getEventi($this->lang);

        $categorie_agganciate = [];
        if(count($eventi) > 0)
        {
            $categorieManager = new CategorieManager($this->conn);
            foreach ($eventi as $item)
            {
                $agganci = $categorieManager->getAgganciCategoria($this->lang,$item->id,'eventi');
                $categorie_agganciate[$item->id] = $agganci;
            }
        }

        $view = new View();
        $view->setVariable('eventi',$eventi);
        $view->setVariable('categorie_agganciate',$categorie_agganciate);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_aggiungi()
    {
        $this->setConfigModulo($this->moduli['eventi']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'eventi');

        $eventiManager = new EventiManager($this->conn);
        $langs = $this->langManager->getAllLangs();


        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {

            $data = $this->request->paramsPost;

            if($this->config_modulo['con_categorie'])
            {

                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($eventiManager->addEvento($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'eventi');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }


        //faccio vedere il modal col form
        $form = 'form-aggiungi-eventi';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','aggiungi');

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setTemplate('pages/modals/eventi_add');
        echo $this->renderManager->render($view);
    }

    protected function _eventi_categorie()
    {
        $this->js_link[] = 'js/eventi.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['eventi']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'eventi');


        $nomi_genitore = [];
        if(count($categorie) > 0)
        {
            foreach($categorie as $cat)
            {
                $id_genitore = $cat->id_genitore;
                if($id_genitore != 0)
                {
                    $genitore = $categorieManager->getCategoria($this->lang,$id_genitore);
                    $nomi_genitore[$cat->id] = $genitore->nome;
                }
                else
                {
                    $nomi_genitore[$cat->id] = 'Nessuno';
                }

            }
        }

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('nomi_genitore',$nomi_genitore);
        $view->setVariable('title_page','categorie eventi');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_modifica()
    {
        $this->setConfigModulo($this->moduli['eventi']['config']);

        $langs            = $this->langManager->getAllLangs();
        $eventiManager    = new EventiManager($this->conn);
        $categorieManager = new CategorieManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if($this->config_modulo['con_categorie'])
            {
                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }
            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($eventiManager->updateEvento($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'eventi','modifica',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_eventi_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi');
            Utils::redirect($url);
            exit();
        }
        $item        = $eventiManager->getEvento($this->lang,$id);
        $categorie   = $categorieManager->getCategorie($this->lang,'eventi');
        $form        = 'form-modifica-eventi';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','modifica');
        $cm          = $this->config_modulo;
        $title_page  = 'Modifica '.$item->nome;

        $view = new View();
        $view->setVariable('item',$item);
        $view->setVariable('categorie', $categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$cm);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_elimina()
    {
        $id = $this->request->getParam('id');

        $this->setConfigModulo($this->moduli['eventi']['config']);

        $categoriaManager   = new CategorieManager($this->conn);
        $fileManager        = new FileManager($this->conn);
        $dbManager          = new DbManager($this->conn);

        if($dbManager->changeStato('tb_eventi',$id,0))
        {
            $files = $fileManager->getFiles($this->lang,'eventi',$id,1);
            if(count($files) > 0)
            {
                foreach($files as $file)
                {
                    $fileManager->elimina($file,$this->config_modulo['upload_config_1']);
                }
            }
            //elimino tutti i record nella tabella tb_file
            $dbManager->deleteFilesOfElement('eventi',$id);
            $categoriaManager->rimuoviCategorie($id,'eventi');
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'eventi');
        Utils::redirect($url);
        exit();
    }

    protected function _eventi_immagini()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['eventi']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi');
            Utils::redirect($url);
            die;
        }

        $eventiManager = new EventiManager($this->conn);
        $item = $eventiManager->getEvento($this->lang,$id_elem);
        $id_tipo = 1;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'eventi';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_file()
    {
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['eventi']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi');
            Utils::redirect($url);
            die;
        }

        $eventiManager = new EventiManager($this->conn);
        $item = $eventiManager->getEvento($this->lang,$id_elem);
        $id_tipo = 2;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'eventi';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $file_presenti = $fileManager->getFiles($this->request->lang, $modulo, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','file');

        $view = new View();
        $view->setVariable('title_page','Files per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('file_presenti',$file_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _eventi_video()
    {
        $this->setConfigModulo($this->moduli['eventi']['config']);

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi');
            Utils::redirect($url);
            die;
        }

        $eventiManager = new EventiManager($this->conn);
        $item = $eventiManager->getEvento($this->lang,$id_elem);
        $langs = $this->langManager->getAllLangs();
        $modulo = 'eventi';

        $videoManager = new VideoManager($this->conn);
        $videos = $videoManager->getVideos($this->lang,$modulo,$id_elem);

        $view = new View();
        $view->setVariable('title_page','Video per '.$item->nome);
        $view->setVariable('videos', $videos);
        $view->setVariable('item', $item);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _eventi_aggiungi_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['eventi']['config']);

        $modulo = 'eventi';

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il link è obbligatorio'];
                echo json_encode($result);
                exit();
            }
            //fine controllo

            $videoManager = new VideoManager($this->conn);
            //faccio l'inserimento
            if($videoManager->addVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'eventi','video',['id'=>$data['id_elem']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $id_elem = $this->request->getParam('id');
        $form = 'form-aggiungi-video';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','aggiungi_video');

        $view = new View();
        $view->setTemplate('pages/modals/video_add');
        $view->setVariable('id_elem',$id_elem);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view);
    }

    protected function _eventi_modifica_video()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['eventi']['config']);
        $videoManager = new VideoManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                echo json_encode($result);
                exit();
            }
            //fine controllo


            //faccio l'aggiornamento
            if($videoManager->updateVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'eventi','modifica_video',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_video_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi','video');
            Utils::redirect($url);
            exit();
        }
        $video = $videoManager->getVideo($this->request->lang,$id);

        $form = 'form-modifica-video';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','modifica_video');

        $title_page = 'Modifica Video '.$video->link;

        $view = new View();
        $view->setVariable('video',$video);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','eventi');
        $view->setVariable('id_news',$video->id_elem);
        $view->setTemplate('pages/video_modifica');
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _eventi_change_visibility()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_eventi';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _eventi_aggiungi_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['eventi']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($categorieManager->addCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'eventi','categorie');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','aggiungi_categoria');
        $categorie   = $categorieManager->getCategorie($this->lang,'eventi');

        $cm = $this->config_modulo;

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('modulo','eventi');
        $view->setVariable('langs',$langs);
        $view->setVariable('lang',$this->lang);
        $view->setVariable('cm',$cm);
        $view->setVariable('categorie',$categorie);
        $view->setTemplate('pages/modals/categorie_add');
        echo $this->renderManager->render($view);
    }

    protected function _eventi_elimina_categoria()
    {
        $id = $this->request->getParam('id');
        $categorieManager = new CategorieManager($this->conn);
        $dbManager = new DbManager($this->conn);

        //controllo che non ci siano elementi attive associata a questa categoria
        if($categorieManager->checkHaveAgganci($id))
        {
            $this->sessionManager->addErrorMessage('Errore! Questa categoria non può essere eliminata...sono presenti elementi associate ad essa');
            $url = $this->requestManager->getUrl($this->lang,'eventi','categorie');
            Utils::redirect($url);
            exit();
        }

        if($dbManager->changeStato('tb_categorie',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'eventi','categorie');
        Utils::redirect($url);
        exit();
    }

    protected function _eventi_modifica_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['eventi']['config']);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($categorieManager->updateCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'eventi','modifica_categoria',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_categorie_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'eventi','categorie');
            Utils::redirect($url);
            exit();
        }
        $categoria       = $categorieManager->getCategoria($this->request->lang,$id);
        $altre_categorie = $categorieManager->getAltreCategorie($categoria->id,$this->lang,'eventi');

        $form = 'form-modifica-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'eventi','modifica_categoria');

        $title_page = 'Modifica Categoria '.$categoria->nome;

        $view = new View();
        $view->setVariable('categoria',$categoria);
        $view->setVariable('altre_categorie', $altre_categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','eventi');
        $view->setTemplate('pages/categorie_modifica');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_index()
    {
        $this->js_link[] = 'js/catalogo.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $view = new View();
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_prodotti_datatable()
    {
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $columns = [];
        $count   = 0;

        //colonna id prodotto
        $columns[] = ['db' => 'id','dt' => $count];
        $count++;

        //colonna del CODICE prodotto
        $columns[] = ['db' => 'codice', 'dt' => $count, 'formatter' => function($codice,$row)
        {
            $url_modifica = $this->getUrl($this->lang,'catalogo','modifica_prodotto',['id'=>$row['id']]);
            $html = sprintf('<a href="%s" title="Modifica">%s</a>',$url_modifica, $codice);
            return $html;
        }];
        $count++;

        //colonna del NOME prodotto
        $columns[] = ['db' => 'nome_it','dt' => $count];
        $count++;

        //colonna del PREZZO
        $columns[] = ['db' => 'prezzo', 'dt' => $count, 'formatter' => function ($prezzo,$row)
        {
            $html = '&euro; <input type="text" name="prezzo" id="prezzo_' . $row['id'] . '" value="' . $prezzo . '" style="width:60px;line-height:1.8" />';
            $html.= '<a class="azioni-table" title="salva" href="javascript:void(0)" onclick="changePrezziProdotto(' . $row['id'] . ')" >';
            $html.= '&nbsp;<i class="fa fa-save fa-2x" style="position:relative;top:5px"></i></a>';
            return $html;
        }];
        $count++;

        //colonna del PREZZO SCONTATO
        $columns[] = ['db' => 'prezzo_scontato', 'dt' => $count, 'formatter' => function ($prezzo_scontato,$row)
        {
            $html = '&euro; <input type="text" name="prezzo_scontato" id="prezzo_scontato_' . $row['id'] . '" value="' . $prezzo_scontato . '" style="width:60px;line-height:1.8" />';
            $html.= '<a class="azioni-table" title="salva" href="javascript:void(0)" onclick="changePrezziProdotto(' . $row['id'] . ')" >';
            $html.= '&nbsp;<i class="fa fa-save fa-2x" style="position:relative;top:5px"></i></a>';
            return $html;
        }];
        $count++;

        //colonna delle Categorie
        if($this->config_modulo['con_categorie'])
        {
            $columns[] = ['db' => 'id', 'dt' => $count, 'formatter' => function ($id,$row)
            {
                $html = '';
                $categorieManager = new CategorieManager($this->conn);
                $categorie = $categorieManager->getAgganciCategoria($this->lang,$id,'prodotti');
                if(count($categorie) > 0)
                {
                    foreach($categorie as $cat)
                    {
                        if(is_object($cat))
                        {
                            $html.= '('.$cat->nome.') ';
                        }
                    }
                }
                return $html;
            }];
            $count++;
        }

        //colonna VISIBILE se ha i privilegi
        if($this->config_modulo['permessi']['visibilita'] >= $this->user->id_role)
        {
            $columns[] = ['db' => 'visibile', 'dt' => $count, 'formatter' => function ($visibile,$row)
            {
                $id_switch    = 'switch_'. $row['id'];
                $url          = $this->getUrl($this->lang,'catalogo', 'change_visibility_prodotto',['id' => $row['id']]);
                $checked      = ($visibile == 1) ? 'checked' : '';
                $html         = $this->renderManager->getPartial('pages/partials/switch_button',['id_switch'=>$id_switch,'url'=>$url,'checked'=>$checked]);
                return $html;
            }];
            $count++;
        }

        //colonna HOMEPAGE se previsto
        if($this->config_modulo['permessi']['homepage'])
        {
            $columns[] = ['db' => 'homepage', 'dt' => $count, 'formatter' => function ($homepage,$row){
                $id_switch    = 'switch_home'. $row['id'];
                $url          = $this->getUrl($this->lang,'catalogo', 'change_homepage_prodotto',['id' => $row['id']]);
                $checked      = ($homepage == 1) ? 'checked' : '';
                $html         = $this->renderManager->getPartial('pages/partials/switch_button',['id_switch'=>$id_switch,'url'=>$url,'checked'=>$checked]);
                return $html;
            }];
            $count++;
        }

        //colonna AZIONI
        $columns[] = ['db' => 'id', 'dt' => $count, 'formatter' => function ($id)
        {
            $html = '';

            //pulsante per MODIFICA
            if($this->config_modulo['permessi']['modifica'] >= $this->user->id_role)
            {
                $url  = $this->getUrl($this->lang,'catalogo','modifica_prodotto',['id' => $id]);
                $html .= sprintf('<a class="pl-1"  href="%s" title="modifica prodotto"><i class="fa fa-edit fa-2x"></i> </a>',$url);
            }
            //pulsante per IMMAGINI
            if($this->config_modulo['permessi']['immagini'] >= $this->user->id_role)
            {
                $url  = $this->getUrl($this->lang,'catalogo','immagini_prodotto',['id' => $id]);
                $html .= sprintf('<a class="pl-1"  href="%s" title="immagini prodotto"><i class="fa fa-camera fa-2x"></i> </a>',$url);
            }
            //pulsante per Video
            if($this->config_modulo['con_video'])
            {
                $url  = $this->getUrl($this->lang,'catalogo','video_prodotto',['id' => $id]);
                $html .= sprintf('<a class="pl-1"  href="%s" title="video prodotto"><i class="fa fa-video-camera fa-2x"></i> </a>',$url);
            }
            //pulsante per PDF
            if($this->config_modulo['con_schede_pdf'])
            {
                $url  = $this->getUrl($this->lang,'catalogo','file_prodotto',['id' => $id]);
                $html .= sprintf('<a class="pl-1"  href="%s" title="pdf prodotto"><i class="fa fa-file-pdf-o fa-2x"></i> </a>',$url);
            }
            //pulsante per ELIMINARE
            if($this->config_modulo['permessi']['elimina'] >= $this->user->id_role)
            {
                $url  = $this->getUrl($this->lang,'catalogo','elimina_prodotto',['id' => $id]);
                $html .= sprintf('<a class="azione-red elimina pl-1"  href="%s" title="elimina prodotto"><i class="fa fa-trash fa-2x"></i> </a>',$url);
            }
            return $html;
        }];
        $count++;

        //colonna IMMAGINE COVER
        $columns[] = ['db' => 'id', 'dt' => $count, 'formatter' => function ($id){
            $html = '';
            $prodotto = $this->catalogoManager->getProdotto($this->lang,$id);
            if($prodotto->cover != '')
            {
                $img_nome = $prodotto->cover->nome;
                $html = sprintf("<a href='/file/%s'><img src='/file/%s' class='img-responsive' style='max-width:50px' /></a>", $img_nome, $img_nome);
            }
            return $html;
        }];

        $whereResult = "stato = 1 ";
        $conn        = $this->conn;
        $request     = $_GET;
        $table       = 'tb_prodotti';
        $primaryKey  = 'id';
        $order       = 'ORDER BY id DESC';

        $data = SSDataTable::complex($request, $conn, $table, $primaryKey, $columns, $whereResult,null, $order);

        echo json_encode($data);
    }

    protected function _catalogo_aggiungi_video_prodotto()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $modulo = 'catalogo';
        $modulo_cat = 'prodotti';

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il link è obbligatorio'];
                echo json_encode($result);
                exit();
            }
            //fine controllo

            $videoManager = new VideoManager($this->conn);
            //faccio l'inserimento
            if($videoManager->addVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'catalogo','video_prodotto',['id'=>$data['id_elem']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $id_elem = $this->request->getParam('id');
        $form = 'form-aggiungi-video';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','aggiungi_video_prodotto');

        $view = new View();
        $view->setTemplate('pages/modals/video_add');
        $view->setVariable('id_elem',$id_elem);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('modulo_cat',$modulo_cat);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view);
    }

    protected function _catalogo_change_prezzi_prodotto()
    {
        $id_prodotto     = $this->request->getParam('id',null);
        $prezzo          = $this->request->getParam('prezzo',null);
        $prezzo_scontato = $this->request->getParam('prezzo_scontato',null);

        if($id_prodotto == null || $prezzo == null)
        {
            exit();
        }

        $result = $this->catalogoManager->updatePrezziProdotto($id_prodotto, $prezzo,$prezzo_scontato);

        if (!$result)
        {
            $data = array('result' => 0, 'msg' => 'Errore durante esecuzione query');
        }
        elseif ($result == 2)
        {
            $data = array('result' => 1, 'msg' => 'Il prezzo non è stato modificato');
        }
        else
        {
            $data = array('result' => 1, 'msg' => 'Prezzo modificato con successo!');
        }

        echo json_encode($data);
    }

    protected function _catalogo_change_visibility_prodotto()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_prodotti';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _catalogo_change_homepage_prodotto()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_prodotti';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeHomepage($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    protected function _catalogo_aggiungi_prodotto()
    {
        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'prodotti');
        foreach($categorie as $cat)
        {
            $cat->nome_genitore = '';
            if($cat->id_genitore != 0)
            {
                $genitore = $categorieManager->getCategoria($this->lang,$cat->id_genitore);
                $cat->nome_genitore = $genitore->nome_it;
            }
        }

        $langs = $this->langManager->getAllLangs();


        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {

            $data = $this->request->paramsPost;

            if($this->config_modulo['con_categorie'])
            {

                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($this->catalogoManager->addProdotto($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'catalogo');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }


        //faccio vedere il modal col form
        $form = 'form-aggiungi-prodotto';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','aggiungi_prodotto');

        $marche = $this->catalogoManager->getMarche();
        $tags   = $this->catalogoManager->getTags($this->lang);

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('marche',$marche);
        $view->setVariable('tags',$tags);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setTemplate('pages/modals/catalogo_add_prodotto');
        echo $this->renderManager->render($view);
    }

    protected function _catalogo_modifica_prodotto()
    {
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $langs            = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if($this->config_modulo['con_categorie'])
            {
                if(!isset($data['id_categorie']) || empty($data['id_categorie']))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! La categoria è obbligatoria'];
                    echo json_encode($result);
                    exit();
                }
            }
            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($this->catalogoManager->updateProdotto($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'catalogo','modifica_prodotto',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_catalogo_modifica_prodotto');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo');
            Utils::redirect($url);
            exit();
        }
        $item        = $this->catalogoManager->getProdotto($this->lang,$id);
        $categorie   = $categorieManager->getCategorie($this->lang,'prodotti');

        foreach($categorie as $cat)
        {
            $cat->nome_genitore = '';
            if($cat->id_genitore != 0)
            {
                $genitore = $categorieManager->getCategoria($this->lang,$cat->id_genitore);
                $cat->nome_genitore = $genitore->nome_it;
            }
        }
        $form        = 'form-modifica-prodotti';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','modifica_prodotto');
        $cm          = $this->config_modulo;
        $marche      = $this->catalogoManager->getMarche();
        $tags        = $this->catalogoManager->getTags($this->lang);
        $title_page  = 'Modifica '.$item->nome;

        $view = new View();
        $view->setVariable('item',$item);
        $view->setVariable('categorie', $categorie);
        $view->setVariable('marche', $marche);
        $view->setVariable('tags', $tags);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$cm);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_elimina_prodotto()
    {
        $id = $this->request->getParam('id');

        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $categoriaManager   = new CategorieManager($this->conn);
        $fileManager        = new FileManager($this->conn);
        $dbManager          = new DbManager($this->conn);

        if($dbManager->changeStato('tb_prodotti',$id,0))
        {
            $files = $fileManager->getFiles($this->lang,'prodotti',$id,1);
            if(count($files) > 0)
            {
                foreach($files as $file)
                {
                    $fileManager->elimina($file,$this->config_modulo['upload_config_1']);
                }
            }
            //elimino tutti i record nella tabella tb_file
            $dbManager->deleteFilesOfElement('prodotti',$id);
            $categoriaManager->rimuoviCategorie($id,'prodotti');
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'catalogo');
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_video_prodotto()
    {
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo');
            Utils::redirect($url);
            die;
        }

        $item = $this->catalogoManager->getProdotto($this->lang,$id_elem);
        $langs = $this->langManager->getAllLangs();
        $modulo = 'catalogo';
        $modulo_cat = 'prodotti';

        $videoManager = new VideoManager($this->conn);
        $videos = $videoManager->getVideos($this->lang,$modulo_cat,$id_elem);

        $view = new View();
        $view->setVariable('title_page','Video per '.$item->nome);
        $view->setVariable('videos', $videos);
        $view->setVariable('item', $item);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('modulo_cat',$modulo_cat);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);

    }

    protected function _catalogo_modifica_video_prodotto()
    {
        $langs = $this->langManager->getAllLangs();
        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $videoManager = new VideoManager($this->conn);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            if(empty($data['link']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                echo json_encode($result);
                exit();
            }
            //fine controllo


            //faccio l'aggiornamento
            $data['modulo'] = 'prodotti';
            if($videoManager->updateVideo($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'catalogo','modifica_video_prodotto',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_video_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo','video_prodotto');
            Utils::redirect($url);
            exit();
        }
        $video = $videoManager->getVideo($this->request->lang,$id);

        $form = 'form-modifica-video';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','modifica_video_prodotto');

        $title_page = 'Modifica Video '.$video->link;

        $view = new View();
        $view->setVariable('video',$video);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','catalogo');
        $view->setVariable('modulo_cat','prodotti');
        $view->setVariable('id',$video->id_elem);
        $view->setTemplate('pages/catalogo_modifica_video_prodotto');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_elimina_video_prodotto()
    {
        $id           = $this->request->getParam('id');
        $videoManager = new VideoManager($this->conn);
        $video = $videoManager->getVideo($this->lang,$id);

        if($videoManager->elimina($id))
        {
            $this->sessionManager->addSuccessMessage('Video eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il video');
        }

        $url = $this->requestManager->getUrl($this->lang, 'catalogo','video_prodotto', ['id'=>$video->id_elem]);
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_categorie()
    {
        $this->js_link[] = 'js/catalogo.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $categorieManager = new CategorieManager($this->conn);
        $categorie = $categorieManager->getCategorie($this->lang,'prodotti');


        $nomi_genitore = [];
        if(count($categorie) > 0)
        {
            foreach($categorie as $cat)
            {
                $id_genitore = $cat->id_genitore;
                if($id_genitore != 0)
                {
                    $genitore = $categorieManager->getCategoria($this->lang,$id_genitore);
                    $nomi_genitore[$cat->id] = $genitore->nome;
                }
                else
                {
                    $nomi_genitore[$cat->id] = 'Nessuno';
                }

            }
        }

        $view = new View();
        $view->setVariable('categorie',$categorie);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('nomi_genitore',$nomi_genitore);
        $view->setVariable('title_page','categorie prodotti');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_modifica_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        //se arrivano i dati POST faccio la modifica
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }
            }
            //fine controllo

            //faccio l'aggiornamento
            if($categorieManager->updateCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Elemento aggiornato');
                $url = $this->requestManager->getUrl($this->lang,'catalogo','modifica_categoria',['id'=>$data['id']]);
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile aggiornare elemento'];
            }

            echo json_encode($result);
            exit();
        }

        $this->addJsScript('pages/scripts/js_categorie_modifica');
        $this->setLayout();

        $id = $this->request->getParam('id',null);
        if($id == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo','categorie');
            Utils::redirect($url);
            exit();
        }
        $categoria       = $categorieManager->getCategoria($this->request->lang,$id);
        $altre_categorie = $categorieManager->getAltreCategorie($categoria->id,$this->lang,'prodotti');

        $form = 'form-modifica-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','modifica_categoria');

        $title_page = 'Modifica Categoria '.$categoria->nome;

        $view = new View();
        $view->setVariable('categoria',$categoria);
        $view->setVariable('altre_categorie', $altre_categorie);
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('title_page',$title_page);
        $view->setVariable('langs',$langs);
        $view->setVariable('cm',$this->config_modulo);
        $view->setVariable('modulo','catalogo');
        $view->setVariable('modulo_cat','prodotti');
        $view->setTemplate('pages/categorie_modifica');
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_marche()
    {
        $this->js_link[] = 'js/catalogo.js';
        $this->setLayout();

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $marche = $this->catalogoManager->getMarche();

        $view = new View();
        $view->setVariable('marche',$marche);
        $view->setVariable('cm', $this->config_modulo);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_aggiungi_marca()
    {
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            //controllo che siano arrivati tutti i dati necessari
            if(empty($data['nome']))
            {
                $result = ['result' => 0, 'msg' => 'Errore! Il campo nome è obbligatorio'];
                echo json_encode($result);
                exit();
            }
            //fine controllo

            //faccio l'inserimento
            if($this->catalogoManager->addMarca($data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');

                $url    = $this->getUrl($this->lang,'catalogo','marche');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form        = 'form-aggiungi-marca';
        $form_action = $this->getUrl($this->lang,'catalogo','aggiungi_marca');

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action', $form_action);
        $view->setVariable('cm', $this->config_modulo);
        $view->setTemplate('pages/modals/catalogo_add_marca');
        echo $this->renderManager->render($view);
    }

    protected function _catalogo_aggiungi_categoria()
    {
        $langs = $this->langManager->getAllLangs();
        $categorieManager = new CategorieManager($this->conn);
        $this->setConfigModulo($this->moduli['catalogo']['config']);

        //se arrivano i dati POST faccio l'inserimento
        if(!empty($this->request->paramsPost))
        {
            $data = $this->request->paramsPost;

            foreach($langs as $lang)
            {
                if(empty($data['nome_'.$lang->sigla]))
                {
                    $result = ['result' => 0, 'msg' => 'Errore! Il nome è obbligatorio per tutte le lingue'];
                    echo json_encode($result);
                    exit();
                }

            }
            //fine controllo

            //faccio l'inserimento
            if($categorieManager->addCategoria($langs,$data))
            {
                $this->sessionManager->addSuccessMessage('Successo! Creato nuovo elemento');
                $url = $this->requestManager->getUrl($this->lang,'catalogo','categorie');
                $result = ['result' => 1, 'msg' => 'Successo!', 'url' => $url];
            }
            else
            {
                $result = ['result' => 0, 'msg' => 'Errore! Impossibile creare il nuovo elemento'];
            }

            echo json_encode($result);
            exit();
        }

        //faccio vedere il modal col form
        $form = 'form-aggiungi-categoria';
        $form_action = $this->requestManager->getUrl($this->lang,'catalogo','aggiungi_categoria');
        $categorie   = $categorieManager->getCategorie($this->lang,'prodotti');

        $cm = $this->config_modulo;

        $view = new View();
        $view->setVariable('form',$form);
        $view->setVariable('form_action',$form_action);
        $view->setVariable('modulo','prodotti');
        $view->setVariable('langs',$langs);
        $view->setVariable('lang',$this->lang);
        $view->setVariable('cm',$cm);
        $view->setVariable('categorie',$categorie);
        $view->setTemplate('pages/modals/categorie_add');
        echo $this->renderManager->render($view);
    }

    protected function _catalogo_elimina_categoria()
    {
        $id = $this->request->getParam('id');
        $categorieManager = new CategorieManager($this->conn);
        $dbManager = new DbManager($this->conn);

        //controllo che non ci siano elementi attive associata a questa categoria
        if($categorieManager->checkHaveAgganci($id))
        {
            $this->sessionManager->addErrorMessage('Errore! Questa categoria non può essere eliminata...sono presenti elementi associate ad essa');
            $url = $this->requestManager->getUrl($this->lang,'catalogo','categorie');
            Utils::redirect($url);
            exit();
        }

        if($dbManager->changeStato('tb_categorie',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'catalogo','categorie');
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_elimina_marca()
    {
        $id = $this->request->getParam('id');
        $dbManager = new DbManager($this->conn);

        if($dbManager->changeStato('tb_marche',$id,0))
        {
            $this->sessionManager->addSuccessMessage('Successo! Elemento eliminato');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! impossibile eliminare l\'elemento');
        }
        $url = $this->requestManager->getUrl($this->lang,'catalogo','marche');
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_immagini_prodotto()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo');
            Utils::redirect($url);
            die;
        }


        $item = $this->catalogoManager->getProdotto($this->lang, $id_elem);
        $id_tipo = 1;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount('prodotti', $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $modulo = 'catalogo';
        $modulo_cat = 'prodotti';

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo_cat, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('modulo_cat',$modulo_cat);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_immagini_marca()
    {
        //carico la jquery ui per il DRAG and DROP dell'ordinamento (non posso metterlo nel layout va in conflitto con summernote)
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo','marche');
            Utils::redirect($url);
            die;
        }


        $item = $this->catalogoManager->getMarca($id_elem);
        $id_tipo = 2;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount('marche', $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $modulo = 'catalogo';
        $modulo_cat = 'marche';

        $immagini_presenti = $fileManager->getFiles($this->request->lang, $modulo_cat, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'upload','immagini');

        $view = new View();
        $view->setVariable('title_page','Immagini per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('modulo_cat',$modulo_cat);
        $view->setVariable('immagini_presenti',$immagini_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_elimina_immagine_marca()
    {
        $id          = $this->request->getParam('id');
        $id_tipo     = $this->request->getParam('id_tipo');
        $fileManager = new FileManager($this->conn);
        $file        = $fileManager->getFile($this->lang, $id);

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        if($fileManager->elimina($file,$fileConfig))
        {
            $this->sessionManager->addSuccessMessage('File eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il file');
        }

        $url = $this->requestManager->getUrl($this->lang, 'catalogo','immagini_marca', ['id'=>$file->id_elem]);
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_elimina_immagine_prodotto()
    {
        $id          = $this->request->getParam('id');
        $id_tipo     = $this->request->getParam('id_tipo');
        $fileManager = new FileManager($this->conn);
        $file        = $fileManager->getFile($this->lang, $id);

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        if($fileManager->elimina($file,$fileConfig))
        {
            $this->sessionManager->addSuccessMessage('File eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il file');
        }

        $url = $this->requestManager->getUrl($this->lang, 'catalogo','immagini_prodotto', ['id'=>$file->id_elem]);
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_file_prodotto()
    {
        $this->js_link[] = 'assets/js/plugins/jquery-ui/jquery-ui.min.js';
        $this->js_link[] = 'assets/js/plugins/touchpunch/jquery.ui.touch-punch.min.js';

        $this->setConfigModulo($this->moduli['catalogo']['config']);

        $this->addJsScript('pages/scripts/js_script_upload');

        $this->setLayout();

        $id_elem = $this->request->getParam('id', null);

        if($id_elem == null)
        {
            $url = $this->requestManager->getUrl($this->lang,'catalogo');
            Utils::redirect($url);
            die;
        }

        $item = $this->catalogoManager->getProdotto($this->lang,$id_elem);
        $id_tipo = 3;
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $modulo = 'catalogo';
        $modulo_cat = 'prodotti';

        //controllo e setto la variabile per il limite max di immagini da caricare
        $fileManager = new FileManager($this->conn);
        $num_file = $fileManager->getCount($modulo_cat, $id_elem, $id_tipo);
        $lim_max_file = false;
        $file_restanti = $fileConfig['max_numero_file'] - $num_file;

        $file_presenti = $fileManager->getFiles($this->request->lang, $modulo_cat, $id_elem, $id_tipo);

        if ($file_restanti <= 0)
        {
            $lim_max_file = true;
        }

        $langs = $this->langManager->getAllLangs();
        $url_upload = $this->requestManager->getUrl($this->lang,'catalogo','upload_file_prodotto');

        $view = new View();
        $view->setVariable('title_page','Files per '.$item->nome);
        $view->setVariable('url_upload',$url_upload);
        $view->setVariable('item', $item);
        $view->setVariable('fileConfig', $fileConfig);
        $view->setVariable('id_tipo',$id_tipo);
        $view->setVariable('file_presenti',$file_presenti);
        $view->setVariable('lim_max_file',$lim_max_file);
        $view->setVariable('num_file',$file_restanti);
        $view->setVariable('modulo',$modulo);
        $view->setVariable('langs',$langs);
        echo $this->renderManager->render($view,$this->layout);
    }

    protected function _catalogo_upload_file_prodotto()
    {
        $id_elem = $this->request->getParam('id_elem');
        $id_tipo = $this->request->getParam('id_tipo');
        $modulo  = 'prodotti';

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $allowed    = explode(',', str_replace('.','', $fileConfig['extensions']));

        $max_file_size = $fileConfig['max_file_size'].'000000';

        $fileManager = new FileManager($this->conn);
        $fileManager->setSeoPrefix($fileConfig['prefix']);
        if(!$fileManager->upload($_FILES['file'], $max_file_size, $allowed))
        {
            $this->sessionManager->addErrorMessage($fileManager->getMessage());
            return;
        }

        $this->sessionManager->addSuccessMessage($fileManager->getMessage());

        //Informazioni del file caricato
        $estensione = $fileManager->getEstensioneFile();
        $nomeFile   = $fileManager->getNomeFile();

        //faccio l'inserimento nel db
        if(!$fileManager->insertInDb($modulo, $id_elem, $id_tipo, $nomeFile.'.'.$estensione))
        {
            $this->sessionManager->addErrorMessage('Impossibile salvare nel db il file '.$nomeFile);
        }

        return;
    }

    protected function _catalogo_elimina_file_prodotto()
    {
        $id          = $this->request->getParam('id');
        $id_tipo     = $this->request->getParam('id_tipo');
        $fileManager = new FileManager($this->conn);
        $file        = $fileManager->getFile($this->lang, $id);

        $this->setConfigModulo($this->moduli['catalogo']['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        if($fileManager->elimina($file,$fileConfig))
        {
            $this->sessionManager->addSuccessMessage('File eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il file');
        }

        $url = $this->requestManager->getUrl($this->lang, 'catalogo','file_prodotto', ['id'=>$file->id_elem]);
        Utils::redirect($url);
        exit();
    }

    protected function _catalogo_sinc()
    {
        $query = "SELECT * FROM sws_hikashop_product";

        $sql   = $this->conn->prepare($query);

        if($sql->execute())
        {
            $rows = $sql->fetchAll();
            if (is_array($rows) && count($rows) > 0)
            {
                foreach ($rows as $row)
                {
                    $nome_prodotto = $row['product_name'];
                    $codice_prodotto = $row['product_code'];
                    $desc_prodotto = $row['product_description'];
                    $id_prodotto = $row['product_id'];
                    $prezzo_prodotto = 0;

                    $query2 = "SELECT * FROM sws_hikashop_price WHERE price_product_id = $id_prodotto";
                    $sql2 = $this->conn->prepare($query2);
                    if($sql2->execute())
                    {
                        $rowsPrice = $sql2->fetchAll();
                        if(isset($rowsPrice[0]['price_value']))
                        {
                            $prezzo_prodotto = $rowsPrice[0]['price_value'];
                        }

                    }

                    $query3 = "INSERT INTO tb_prodotti (id,nome_it,codice,desc_it,prezzo) VALUES (:id,:nome_prodotto,:codice_prodotto,:desc_prodotto,:prezzo_prodotto)";
                    $sql3 = $this->conn->prepare($query3);
                    $sql3->bindParam(':id',$id_prodotto, \PDO::PARAM_INT);
                    $sql3->bindParam(':nome_prodotto',$nome_prodotto, \PDO::PARAM_STR);
                    $sql3->bindParam(':codice_prodotto',$codice_prodotto, \PDO::PARAM_STR);
                    $sql3->bindParam(':desc_prodotto',$desc_prodotto, \PDO::PARAM_STR);
                    $sql3->bindParam(':prezzo_prodotto',$prezzo_prodotto, \PDO::PARAM_STR);
                    $sql3->execute();

                    /*$query4 = "SELECT * FROM sws_hikashop_product_category WHERE product_id = $id_prodotto";
                    $sql4 = $this->conn->prepare($query4);
                    if($sql4->execute())
                    {
                        $rowsCat = $sql4->fetchAll();
                        if(is_array($rowsCat) && count($rowsCat) > 0)
                        {
                            foreach ($rowsCat as $row)
                            {
                                $id_categoria = $row['category_id'];
                                $query5 = "INSERT INTO tb_categorie_anchor (id_elem,id_categoria,modulo) VALUES (:id_elem,:id_categoria,'prodotti')";
                                $sql5 = $this->conn->prepare($query5);
                                $sql5->bindParam(':id_elem',$id_prodotto,\PDO::PARAM_INT);
                                $sql5->bindParam(':id_categoria',$id_categoria,\PDO::PARAM_INT);
                                $sql5->execute();
                            }


                        }
                    }*/

                }
            }
        }

    }

    protected function _file_modifica()
    {
        $id          = $this->request->getParam('id');
        $fileManager = new FileManager($this->conn);
        $langs       = $this->langManager->getAllLangs();

        if(!$fileManager->modifica_file($langs, $id, $this->request->getParamsPost()))
        {
            $result = array('result' => 0, 'message' => 'Errore! aggiornamento fallito!');
        }
        else
        {
            $result = array('result' => 1, 'message' => 'Elemento aggiornato con successo!');
        }

        echo json_encode($result);
    }

    protected function _file_order()
    {
        $fileManager = new FileManager($this->conn);
        $pos    = trim($_POST['pos']);
        $modulo = $_POST['modulo'];
        $fileManager->order($pos,$modulo);
    }

    protected function _file_elimina()
    {
        $id          = $this->request->getParam('id');
        $modulo      = $this->request->getParam('modulo');
        $id_tipo     = $this->request->getParam('id_tipo');
        $fileManager = new FileManager($this->conn);
        $file        = $fileManager->getFile($this->lang, $id);

        $this->setConfigModulo($this->moduli[$modulo]['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];

        if($fileManager->elimina($file,$fileConfig))
        {
            $this->sessionManager->addSuccessMessage('File eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il file');
        }

        $info = pathinfo($file->nome);
        $extension = $info['extension'];

        $img_extension = ['jpg','JPG','jpeg','png','gif'];
        if(in_array($extension,$img_extension))
        {
            $url = $this->requestManager->getUrl($this->lang, $modulo,'immagini', ['id'=>$file->id_elem]);
        }
        else
        {
            $url = $this->requestManager->getUrl($this->lang, $modulo,'file', ['id'=>$file->id_elem]);
        }

        Utils::redirect($url);
        exit();
    }

    protected function _video_elimina()
    {
        $id           = $this->request->getParam('id');
        $modulo       = $this->request->getParam('modulo');
        $videoManager = new VideoManager($this->conn);
        $video = $videoManager->getVideo($this->lang,$id);

        if($videoManager->elimina($id))
        {
            $this->sessionManager->addSuccessMessage('Video eliminato con successo!');
        }
        else
        {
            $this->sessionManager->addErrorMessage('Errore! Impossibile eliminare il video');
        }

        $url = $this->requestManager->getUrl($this->lang, $modulo,'video', ['id'=>$video->id_elem]);
        Utils::redirect($url);
        exit();
    }

    protected function _upload_immagini()
    {
        $id_elem = $this->request->getParam('id_elem');
        $id_tipo = $this->request->getParam('id_tipo');
        $modulo  = $this->request->getParam('modulo');
        $modulo_cat = $this->request->getParam('modulo_cat');

        //varibile per i moduli come il catalogo che hanno più sotto moduli es marche prodotti tags
        if($modulo_cat == null) $modulo_cat = $modulo;

        $this->setConfigModulo($this->moduli[$modulo]['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $allowed    = explode(',', str_replace('.','', $fileConfig['extensions']));

        $max_file_size = $fileConfig['max_file_size'].'000000';

        $fileManager = new FileManager($this->conn);
        $fileManager->setSeoPrefix($fileConfig['prefix']);
        if(!$fileManager->upload($_FILES['file'], $max_file_size, $allowed))
        {
            $this->sessionManager->addErrorMessage($fileManager->getMessage());
            return;
        }

        $this->sessionManager->addSuccessMessage($fileManager->getMessage());

        //Informazioni del file caricato
        $filePath   = $fileManager->getFilePath();
        $estensione = $fileManager->getEstensioneFile();
        $nomeFile   = $fileManager->getNomeFile();

        //faccio l'inserimento nel db
        if(!$fileManager->insertInDb($modulo_cat, $id_elem, $id_tipo, $nomeFile.'.'.$estensione))
        {
            $this->sessionManager->addErrorMessage('Impossibile salvare nel db il file '.$nomeFile);
        }

        $imageManager = new ImageManager();

        //se è settata nella configurazione creo la crop default per l'immagine
        if($fileConfig['crop'])
        {
            $w       = $fileConfig['default_crop_x'];
            $h       = $fileConfig['default_crop_y'];
            $quality = $fileConfig['quality'];

            if($imageManager->makeCrop($w, $h, $filePath, $quality))
            {
                $this->sessionManager->addSuccessMessage('Creata la crop '.$w.'x'.$h.' per '.$nomeFile);
            }
            else
            {
                $this->sessionManager->addErrorMessage('Errore! impossibile creare la crop.');
            }
        }

        //se previste faccio le THUMB
        if(isset($fileConfig['resizes']) && count($fileConfig['resizes']) > 0)
        {
            $quality = $fileConfig['quality'];

            foreach ($fileConfig['resizes'] as $resize)
            {
                $width  = $resize['width'];
                $height = $resize['height'];

                if($imageManager->makeThumb($filePath, $width, $height, $quality))
                {
                    $this->sessionManager->addSuccessMessage('Creata la thumb '.$width.'x'.$height.' per '.$nomeFile);
                }
                else
                {
                    $this->sessionManager->addErrorMessage($imageManager->getMessage());
                }
            }
        }

        return;
    }

    protected function _upload_file()
    {
        $id_elem = $this->request->getParam('id_elem');
        $id_tipo = $this->request->getParam('id_tipo');
        $modulo  = $this->request->getParam('modulo');

        $this->setConfigModulo($this->moduli[$modulo]['config']);
        $fileConfig = $this->config_modulo['upload_config_'.$id_tipo];
        $allowed    = explode(',', str_replace('.','', $fileConfig['extensions']));

        $max_file_size = $fileConfig['max_file_size'].'000000';

        $fileManager = new FileManager($this->conn);
        $fileManager->setSeoPrefix($fileConfig['prefix']);
        if(!$fileManager->upload($_FILES['file'], $max_file_size, $allowed))
        {
            $this->sessionManager->addErrorMessage($fileManager->getMessage());
            return;
        }

        $this->sessionManager->addSuccessMessage($fileManager->getMessage());

        //Informazioni del file caricato
        $estensione = $fileManager->getEstensioneFile();
        $nomeFile   = $fileManager->getNomeFile();

        //faccio l'inserimento nel db
        if(!$fileManager->insertInDb($modulo, $id_elem, $id_tipo, $nomeFile.'.'.$estensione))
        {
            $this->sessionManager->addErrorMessage('Impossibile salvare nel db il file '.$nomeFile);
        }

        return;
    }

    protected function _categorie_change_visibility()
    {
        $id = $this->request->getParam('id');
        $stato = $this->request->getParam('stato');
        $table = 'tb_categorie';

        $dbManager = new DbManager($this->conn);

        if($dbManager->changeVisibility($table, $id, $stato))
        {
            return true;
        }
        return false;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getUrl($lang,$controller,$action='index',$params=null)
    {
        return $this->requestManager->getUrl($lang,$controller,$action,$params);
    }

    protected function setLayout($name = 'layout')
    {
        $this->layout = new View();
        $this->layout->setTemplate('layouts/'.$name);

        $this->layout->setVariable('js_link',$this->js_link);
        $this->layout->setVariable('css_link',$this->css_link);
        $this->layout->setVariable('flashmessages',$this->sessionManager->getFlashMessages());
        $this->layout->setVariable('user', $this->user);
        $this->layout->setVariable('request', $this->request);
        $this->layout->setVariable('lang',$this->lang);
        $this->layout->setVariable('controller',$this->controller);
        $this->layout->setVariable('action',$this->action);
        $this->layout->setVariable('moduli',$this->moduli);

        //$this->layout->setVariable('cm',$this->config_modulo);
        //$this->layout->setVariable('modulo',$this->modulo);

        //per caricare css e js settati dal controller
        $this->layout->setVariable('js_view',$this->js_view);

        return $this;
    }

    protected function checkPrivileges($role)
    {
        //controllo che il ruolo non sia maggiore di quello necessario altrimenti va alla home
        if($this->user->id_role > $role)
        {
            $this->sessionManager->addErrorMessage('non hai le credenziali necessarie per accedere a questo modulo!');
            $url = $this->getUrl($this->lang,'home');
            Utils::redirect($url);
            exit();
        }

    }

    protected function setConfigModulo($file)
    {
        $config_modulo = [];
        require_once ($_SERVER['DOCUMENT_ROOT'].'/admin/moduli/'.$file);
        $this->config_modulo = $config_modulo;
    }

    protected function addJsScript($script)
    {
        $js_view = new View();
        $js_view->setTemplate($script);
        $this->js_view[] = $js_view;
        return;
    }

    protected function getConfigModulo($modulo)
    {
        $config_modulo = [];
        include ($_SERVER['DOCUMENT_ROOT'].'/admin/moduli/'.$this->moduli[$modulo]['config']);
        return $config_modulo;
    }

    /*
     * funzione per debug sessionManager
     */
    protected function debugSessionManager()
    {
        echo "function getId()  ".$this->sessionManager->getId() .'<br><br>';
        echo '<p>function getAll()</p>';
        echo '<pre>';
        print_r($this->sessionManager->getAll());
        echo '</pre><br>';
        exit();
    }
}