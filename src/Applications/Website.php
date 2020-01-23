<?php
namespace Applications;

use Categorie\CategorieManager;
use Eventi\EventiManager;
use FotoGallery\FotoGalleryManager;
use Catalogo\CatalogoManager;
use PDO;
use Session\SessionManager;
use Session\SessionKey;
use Request\RequestManager;
use Request\Model\Request;
use Auth\AuthManager;
use Lang\LangManager;
use Auth\Model\User;
use Renderer\RenderManager;
use Renderer\Model\View;
use Mail\MailService;
use Seo\SeoManager;
use Utils\Log;
use Utils\Utils;
use Admin\Config;

class Website
{
    const ROOT         = '/';
    const FOLDER       = '/site/';
    const SESSION_NAME = 'website';
    const TB_USER      = 'tb_users';
    const TB_ATTEMPTS  = 'tb_attempts';
    const TB_ROLES     = 'tb_roles';

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
     * @var SeoManager
     */
    private $seoManager;

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

    public function __construct(PDO $conn)
    {
        $this->conn   = $conn;   // connessione al db

        $this->sessionManager = new SessionManager(self::SESSION_NAME);
        $this->requestManager = new RequestManager($conn,self::ROOT);

        //in base alla url, cerco nel db e stabilisco la request (lang,controller,action ed eventuali parametri)
        $this->requestManager->setRequestByDb();
        $this->request = $this->requestManager->getRequest();

        $this->authManager = new AuthManager($conn, $this->sessionManager ,self::TB_USER, self::TB_ATTEMPTS,self::TB_ROLES);
        $this->is_auth = $this->authManager->isAuth();

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

        $this->renderManager   = new RenderManager(self::FOLDER, $this);
        $this->seoManager      = new SeoManager($this->conn,$this->request);
        $this->catalogoManager = new CatalogoManager($this->conn);

        if(method_exists($this, '_'.$this->controller . '_'.$this->action))
        {
            return $this->{'_'.$this->controller . '_'.$this->action}();
        }
        else
        {
            if(Config::IN_COSTRUZIONE == false)
            {
                Log::warn('url_errate',$_SERVER['REQUEST_URI']);
                Utils::redirect301(Config::HTTP_PROTOCOL.'://'.$_SERVER['HTTP_HOST']);
            }
            echo "Attenzione il metodo _ $this->controller _ $this->action non esiste!";
        }
        return false;
    }

    protected function _site_index()
    {
        $this->seoManager->setSeo();
        $this->setLayout();

        $prodottiHomepage = $this->catalogoManager->getProdottiHomepage($this->lang,4);

        $eventiManager = new EventiManager($this->conn);
        $eventi = $eventiManager->getEventi($this->lang, 1,1,3);

        $view = new View();
        $view->setVariable('prodotti',$prodottiHomepage);
        $view->setVariable('eventi',$eventi);
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _site_invia_form_contatti()
    {
        $form_nome = 'formcontatti';
        $this->sessionManager->setForm($form_nome);

        //controllo il recaptcha
        if (!$this->verifyGoogleRecaptcha())
        {
            $data = array('result' => 0, 'message' => _('Il Codice di controllo &egrave; errato!')); // se il captcha non è arrivato esco con messaggio errore
            echo json_encode($data);
            exit();
        }
        //controllo email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
        {
            $data = array('result' => 0, 'message' => _("L'indirizzo email non &egrave; valido")); // indirizzo email errato esco con messaggio errore
            echo json_encode($data);
            exit();
        }

        //invio email
        $mailService = new MailService(self::FOLDER);
        if ($mailService->invia_email_contatti($_POST))
        {
            $data = array('result' => 1, 'message' => _("Grazie per averci contattato.<br/><br/>Ti risponderemo al pi&ugrave; presto") . '<script>ga("send", "event", "formcontatti", "submit")</script>');
        }
        else
        {
            $data = array('result' => 0, 'message' => _("Si &egrave; verificato un errore"));
        }

        echo json_encode($data);
        exit();
    }

    protected function _site_informativa()
    {
        $this->seoManager->setSeo();
        $this->setLayout();

        $view = new View();
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _site_policy()
    {
        $this->seoManager->setSeo();
        $this->setLayout();

        $view = new View();
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _site_accept_cookies()
    {
        $_POST = array_map("trim", $_POST); // se esternamente ad uno switch
        $expiration_date = time() + (10 * 365 * 24 * 60 * 60); // in 10 years => Faccio scadere il cookie in un futuro abbastanza lontano
        setcookie("c_acceptance", "yes", $expiration_date, "/");
    }

    protected function _catalogo_index()
    {
        $this->seoManager->setSeo();
        $this->setLayout();

        $categorieManager = new CategorieManager($this->conn);
        $macros = $categorieManager->getCategorie($this->lang,'prodotti','0');

        $macro_con_sub = [];
        foreach($macros as $macro)
        {
            $categorie = $categorieManager->getCategorie($this->lang,'prodotti',$macro->id);

            $categorie_con_prodotti = [];
            if(count($categorie) > 0)
            {
                foreach($categorie as $cat)
                {
                    $prodotti = $this->catalogoManager->getProdottiByCategory($this->lang,$cat->id,1);
                    $cat->prodotti = $prodotti;
                    $categorie_con_prodotti[] = $cat;
                }
            }
            $macro->sottoCategorie = $categorie_con_prodotti;
            $macro_con_sub[] = $macro;
        }

        $view = new View();
        $view->setVariable('macros',$macro_con_sub);
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _catalogo_categoria()
    {
        $id = $this->request->getParam('id');
        if($id == null)
        {
            $url = $this->getUrl($this->lang,'site','index');
            Utils::redirect($url);
        }

        $id = Utils::decript($id);
        $categoriaManager = new CategorieManager($this->conn);
        $categoria = $categoriaManager->getCategoria($this->lang,$id);

        if(!$categoria)
        {
            $view = new View();
            $view->setTemplate('pages/catalogo_categoria_not_found');
            echo $this->renderManager->render($view, $this->layout);
        }

        $prodotti = $this->catalogoManager->getProdottiByCategory($this->lang,$id,1);

        $this->seoManager->setSeo();
        $this->setLayout();

        $view = new View();
        $view->setVariable('prodotti',$prodotti);
        $view->setVariable('categoria',$categoria);
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _catalogo_scheda_prodotto()
    {
        $id = $this->request->getParam('id');
        if($id == null)
        {
            $url = $this->getUrl($this->lang,'site','index');
            Utils::redirect($url);
        }

        $id = Utils::decript($id);
        $prodotto = $this->catalogoManager->getProdotto($this->lang,$id);


        if(!$prodotto)
        {
            $view = new View();
            $view->setTemplate('pages/catalogo_prodotto_not_found');
            echo $this->renderManager->render($view, $this->layout);
        }

        $this->seoManager->setSeo();
        $this->setLayout();

        $view = new View();
        $view->setVariable('prodotto',$prodotto);
        echo $this->renderManager->render($view, $this->layout);
    }

    protected function _catalogo_cerca()
    {
        $search = $this->request->getParam('search');

        if($search == null)
        {
            $url = $this->getUrl($this->lang,'site','index');
            Utils::redirect($url);
        }

        $prodotti = $this->catalogoManager->getProdottiByString($this->lang,$search);

        $this->seoManager->setSeo();
        $this->setLayout();

        $view = new View();
        $view->setVariable('prodotti',$prodotti);
        echo $this->renderManager->render($view, $this->layout);

    }

    protected function _catalogo_invia_form_info_prodotto()
    {
        $form_nome = 'forminfoprodotto';
        $this->sessionManager->setForm($form_nome);

        //controllo il recaptcha
        if (!$this->verifyGoogleRecaptcha())
        {
            $data = array('result' => 0, 'message' => _('Il Codice di controllo &egrave; errato!')); // se il captcha non è arrivato esco con messaggio errore
            echo json_encode($data);
            exit();
        }
        //controllo email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
        {
            $data = array('result' => 0, 'message' => _("L'indirizzo email non &egrave; valido")); // indirizzo email errato esco con messaggio errore
            echo json_encode($data);
            exit();
        }

        //invio email
        $mailService = new MailService(self::FOLDER);
        if ($mailService->invia_email_info_prodotto($_POST))
        {
            $data = array('result' => 1, 'message' => _("Grazie per averci contattato.<br/><br/>Ti risponderemo al pi&ugrave; presto") . '<script>ga("send", "event", "formcontatti", "submit")</script>');
        }
        else
        {
            $data = array('result' => 0, 'message' => _("Si &egrave; verificato un errore"));
        }

        echo json_encode($data);
        exit();
    }

    protected function setLayout($name = 'layout')
    {
        $this->layout = new View();
        $this->layout->setTemplate('layouts/'.$name);

        $categorieManager = new CategorieManager($this->conn);
        $macro = $categorieManager->getCategorie($this->lang,'prodotti','0');

        $macro_con_sub = [];
        foreach($macro as $cat)
        {
            $categorie = $categorieManager->getCategorie($this->lang,'prodotti',$cat->id);
            $cat->sottoCategorie = $categorie;
            $macro_con_sub[] = $cat;
        }

        $search_action = $this->getUrl($this->lang,'catalogo','cerca');

        $this->layout->setVariable('flashmessages',$this->sessionManager->getFlashMessages());
        $this->layout->setVariable('user', $this->user);
        $this->layout->setVariable('request', $this->request);
        $this->layout->setVariable('lang',$this->lang);
        $this->layout->setVariable('controller',$this->controller);
        $this->layout->setVariable('action',$this->action);
        $this->layout->setVariable('langs',$this->langManager->getAllLangs());
        $this->layout->setVariable('seo',$this->seoManager->getSeo());
        $this->layout->setVariable('macrocategorie',$macro_con_sub);
        $this->layout->setVariable('search_action',$search_action);

        //per caricare librerie js e script settati dal controller
        $this->layout->setVariable('js_view',$this->js_view);

        return $this;
    }

    private function verifyGoogleRecaptcha()
    {
        if (!isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response']))
        {
            return false;
        }

        $secret = Config::RECAPTCHA_SECRET;
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']);
        $responseData = json_decode($verifyResponse);

        if ($responseData->success)
        {
            return true;
        }
        return false;
    }

    protected function addJsScript($script)
    {
        $js_view = new View();
        $js_view->setTemplate($script);
        $this->js_view[] = $js_view;
        return;
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

    public function getRequest()
    {
        return $this->request;
    }

    public function getFormData($form)
    {
        return $this->sessionManager->getForm($form);
    }

    public function getUrl($lang,$controller,$action='index',$params=null)
    {
        return $this->requestManager->getUrl($lang,$controller,$action,$params);
    }

    public function getSeoUrl($lang, $controller, $action = 'index', $params = null)
    {
        return $this->requestManager->getSeoUrl($lang,$controller,$action,$params);
    }
}