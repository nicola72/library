<?php

namespace Auth;

use Session\SessionManager;
use Session\SessionKey;
use Auth\Model\User;
use PDO;
use Utils\Log;
use Utils\Utils;

class AuthManager
{
    const HASH_LENGTH = 40;
    const TOKEN_LENGTH = 20;
    const EMAIL_MAX_LENGTH = 100;
    const PASSWORD_MIN_LENGTH = 5;
    const PASSWORD_MAX_LENGTH = 12;
    const ATTACK_MITIGATION_TIME = '+30 minutes';
    const BCRYPT_COST = 10;

    /**
     * @var PDO
     */
    protected $conn;

    /**
     * @var SessionManager
     */
    protected $sessionManager;
    protected $tb;
    protected $tb_attempts;
    protected $tb_roles;
    protected $currentuser = null;
    protected $messages = [];
    protected $recaptcha_config = [];

    /**
     * @var User
     */
    protected $user;

    public $isAuthenticated = false;

    public function __construct($conn,SessionManager $sessionManager, $tb, $tb_attempts, $tb_roles)
    {
        $this->conn           = $conn;
        $this->tb             = $tb;
        $this->tb_attempts    = $tb_attempts;
        $this->tb_roles       = $tb_roles;
        $this->sessionManager = $sessionManager;

        $user = $this->sessionManager->get(SessionKey::USER);

        //se non è presente nella sessione setto un oggetto User Guest
        if(!$user)
        {
            $this->user = new User();
            $this->sessionManager->set(SessionKey::USER, json_encode($this->user));
            return $this;
        }

        //se presente setto l'user con quello della sessione
        $this->user = $user;
        return $this;
    }

    public function login($email, $password, $remember = 0, $captcha_response = null)
    {
        //$block_status = $this->isBlocked();

        /*if ($block_status == "verify") {
            if ($this->checkCaptcha($captcha_response) == false) {
                $return['message'] = $this->__lang("user_verify_failed");

                return $return;
            }
        }*/

        /*if ($block_status == "block") {
            $return['message'] = $this->__lang("user_blocked");
            return $return;
        }*/

        if (!$this->validateEmail($email))
        {
            return false;
        }

        if (!$this->validatePassword($password))
        {
            return false;
        }

        $userId = $this->getUserId(strtolower($email));

        if (!$userId)
        {
            $this->addAttempt();
            $this->messages[] = _('Username o pass errati!');

            return false;
        }

        $user = $this->getBaseUser($userId);

        if (!$this->password_verify_md5($password, $user['password']))
        {
            $this->addAttempt();
            $this->messages[] = _('Username o password errati!');

            return false;
        }

        if ($user['isactive'] != 1)
        {
            $this->addAttempt();
            $this->messages[] = _('Account non attivo!');

            return false;
        }


        //creo e setto l'oggetto User
        $this->user = new User();
        $this->user->setId($user['id']);
        $this->user->setEmail($user['email']);
        $this->user->setUsername($user['email']);
        $this->user->setId_role($user['id_role']);

        //setto la varibile is_auth nella sessione
        $this->sessionManager->set(SessionKey::IS_AUTH,1);
        //setto l'oggetto user nella sessione
        $this->sessionManager->set(SessionKey::USER,json_encode($this->user));

        $this->messages[] = _('Login avvenuto con successo!');

        return true;
    }

    public function register($email, $password, $repeatpassword, $params = [], $captcha_response = null, $use_email_activation = null)
    {

    }

    public function activate($activate_token)
    {

    }

    public function requestReset($email, $use_email_activation = null)
    {

    }

    public function logout()
    {
        //creo e setto l'oggetto User GUEST
        $this->user = new User();

        //elimino la varibile is_auth nella sessione
        $this->sessionManager->delete(SessionKey::IS_AUTH);
        //setto l'oggetto user nella sessione
        $this->sessionManager->set(SessionKey::USER,json_encode($this->user));
    }

    public function logoutAll($uid)
    {

    }

    public function getHash($password)
    {

    }

    public function getUserId($email)
    {
        $query = "SELECT id FROM {$this->tb} WHERE email = :email";

        $sql = $this->conn->prepare($query);
        $sql->execute(['email' => $email]);

        if ($sql->rowCount() == 0)
        {
            return false;
        }

        return $sql->fetchColumn();
    }

    protected function addSession($uid, $remember)
    {

    }

    protected function deleteExistingSessions($uid)
    {

    }

    protected function deleteSession($hash)
    {

    }

    public function checkSession($hash)
    {

    }

    public function getSessionUserId($hash)
    {

    }

    public function isEmailTaken($email)
    {

    }

    public function isEmailBanned($email)
    {

    }

    public function addUser($data)
    {
        $email = $data['username'];
        $password = md5($data['password']);
        $id_role  = $data['id_ruolo'];

        $querycampi = "";
        $queryvalori = "";

        $querycampi  .= "email,";
        $queryvalori .= ":email,";
        $querycampi  .= "password,";
        $queryvalori .= ":password,";
        $querycampi  .= "id_role,";
        $queryvalori .= ":id_role,";

        $querycampi = Utils::eliminaUltimo($querycampi);
        $queryvalori = Utils::eliminaUltimo($queryvalori);
        $query = "INSERT INTO {$this->tb} (" . $querycampi . ") VALUES (" . $queryvalori . ")";
        $sql = $this->conn->prepare($query);


        $sql->bindParam(':email',$email,\PDO::PARAM_STR);
        $sql->bindParam(':password',$password,\PDO::PARAM_STR);
        $sql->bindParam(':id_role',$id_role,\PDO::PARAM_INT);

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

    protected function getBaseUser($userId)
    {
        $query = "SELECT * FROM {$this->tb} WHERE id = :id";
        $sql = $this->conn->prepare($query);
        $sql->execute(['id' => $userId]);

        $data = $sql->fetch(\PDO::FETCH_ASSOC);

        if (!$data)
        {
            return false;
        }

        $data['uid'] = $userId;

        return $data;
    }

    public function getUsers()
    {
        $query = "SELECT a.*,b.nome AS ruolo FROM {$this->tb} AS a LEFT JOIN {$this->tb_roles} AS b ON a.id_role = b.id";
        $sql = $this->conn->prepare($query);

        $utenti = [];
        if($sql->execute())
        {
            while($data = $sql->fetch(\PDO::FETCH_ASSOC))
            {
                $utenti[] = $data;
            }

            return $utenti;
        }
        return false;

    }

    public function getUser($uid, $withpassword = false)
    {

    }

    public function deleteUser($uid)
    {
        $query = "DELETE FROM {$this->tb} WHERE id=:id";
        $sql = $this->conn->prepare($query);
        $sql->bindParam(':id',$uid,\PDO::PARAM_INT);

        if($sql->execute())
        {
            return true;
        }
        return false;
    }

    public function deleteUserForced($uid)
    {

    }

    protected function addRequest($uid, $email, $type, &$use_email_activation)
    {

    }

    public function getRequest($key, $type)
    {

    }

    protected function deleteRequest($id)
    {

    }

    protected function validatePassword($password)
    {

        if (strlen($password) < (int)self::PASSWORD_MIN_LENGTH)
        {
            $this->messages[] = _('Errore! I caratteri devono essere almeno ') . self::PASSWORD_MIN_LENGTH;
            return false;
        }
        elseif (strlen($password) > (int)self::PASSWORD_MAX_LENGTH)
        {
            $this->messages[] = _('Errore! Icaratteri non devono essere più di ') . self::PASSWORD_MIN_LENGTH;
            return false;
        }

        return true;
    }

    protected function validateEmail($email)
    {

        if (strlen($email) > (int)self::EMAIL_MAX_LENGTH)
        {
            $this->messages[] = _('Errore! Lunghezza massima indirizzo email: ') . self::EMAIL_MAX_LENGTH;
            return false;

        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            $this->messages[] = _('Errore! Indirizzo email non valido.');
            return false;

        }

        /*if ((int)$this->config->verify_email_use_banlist && $this->isEmailBanned($email)) {
            $this->addAttempt();
            $state['message'] = $this->__lang("email_banned");

            return $state;
        }*/

        return true;
    }

    public function resetPass($key, $password, $repeatpassword, $captcha_response = null)
    {

    }

    public function resendActivation($email, $use_email_activation = null)
    {

    }

    public function changePassword($uid, $currpass, $newpass, $repeatnewpass, $captcha_response = null)
    {

    }

    public function changeEmail($uid, $email, $password, $captcha = null)
    {

    }

    public function isBlocked()
    {

    }

    protected function checkCaptcha($captcha)
    {

    }

    protected function checkReCaptcha($captcha_response)
    {

    }

    protected function addAttempt()
    {
        $ip = $this->getIp();
        $attempt_expiredate = date("Y-m-d H:i:s", strtotime(self::ATTACK_MITIGATION_TIME));

        $query = "INSERT INTO {$this->tb_attempts} (ip, expiredate) VALUES (:ip, :expiredate)";
        $query_prepared = $this->conn->prepare($query);
        return $query_prepared->execute([
            'ip'         => $ip,
            'expiredate' => $attempt_expiredate
        ]);
    }

    protected function deleteAttempts($ip, $all = false)
    {

    }

    public function getRandomKey($length = self::TOKEN_LENGTH)
    {

    }

    protected function getIp()
    {
        if (getenv('HTTP_CLIENT_IP'))
        {
            $ipAddress = getenv('HTTP_CLIENT_IP');
        }
        elseif (getenv('HTTP_X_FORWARDED_FOR'))
        {
            $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif (getenv('HTTP_X_FORWARDED'))
        {
            $ipAddress = getenv('HTTP_X_FORWARDED');
        }
        elseif (getenv('HTTP_FORWARDED_FOR'))
        {
            $ipAddress = getenv('HTTP_FORWARDED_FOR');
        }
        elseif (getenv('HTTP_FORWARDED'))
        {
            $ipAddress = getenv('HTTP_FORWARDED');
        }
        elseif (getenv('REMOTE_ADDR'))
        {
            $ipAddress = getenv('REMOTE_ADDR');
        }
        else
        {
            $ipAddress = '127.0.0.1';
        }

        return $ipAddress;
    }

    public function getCurrentSessionHash()
    {

    }

    public function isAuth()
    {
        $auth = $this->sessionManager->get(SessionKey::IS_AUTH);
        if($auth == 1)
        {
            return true;
        }
        return false;
    }

    public function getCurrentUser()
    {

    }

    public function comparePasswords($userid, $password_for_check)
    {

    }

    public function password_verify_md5($password, $userPassword)
    {
        $result = (md5($password) == $userPassword) ? true : false;
        return $result;
    }

    public function password_verify_with_rehash($password, $hash, $userId)
    {
        if (!password_verify($password, $hash))
        {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => self::BCRYPT_COST]))
        {
            $hash = $this->getHash($password);

            $query = "UPDATE {$this->tb} SET password = ? WHERE id = ?";
            $query_prepared = $this->conn->prepare($query);
            $query_prepared->execute([$hash, $userId]);
        }

        return true;
    }

    public function updateUser($uid, $params)
    {

    }

    public function getCurrentUserId()
    {
        return $this->getSessionUserId($this->getCurrentSessionHash());
    }

    public function getCurrentSessionUserInfo()
    {

    }

    private function deleteExpiredAttempts()
    {

    }


    private function deleteExpiredSessions()
    {

    }

    private function deleteExpiredRequests()
    {

    }

    public function cron()
    {
        $this->deleteExpiredAttempts();
        $this->deleteExpiredSessions();
        $this->deleteExpiredRequests();
    }

    public function getMessages()
    {
        return $this->messages;
    }
}