<?php
namespace Session;

class SessionManager
{
    /**
     * @var string $name The name of the session.
     */
    private $name = "";

    /**
     * Create a new instance.
     *
     * @param string $name The name of the session
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the session ID.     *
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Get a value from the session data cache.     *
     * @param string $key The name of the name to retrieve     *
     * @return mixed
     */
    public function get(string $key)
    {
        //$this->init();
        if(!isset($_SESSION[$this->name]))
        {
            return false;
        }

        if (!array_key_exists($key, $_SESSION[$this->name]))
        {
            return false;
        }

        return unserialize($_SESSION[$this->name][$key]);
    }


    /**
     * Get all the current session data.     *
     * @return array
     */
    public function getAll(): array
    {
        return $_SESSION[$this->name];
    }


    /**
     * Set a value within session data.     *
     * @param string|array $data Either the name of the session key to update, or an array of keys to update
     * @param mixed $value If $data is a string then store this value in the session data     *
     * @return SessionManager
     */
    public function set($data, $value = null)
    {
        $_SESSION[$this->name][$data] = serialize($value);
        return $this;
    }

    public function setForm($form_nome)
    {
        if(!isset($_SESSION[$form_nome]))
        {
            $_SESSION[$form_nome] =[];
        }

        foreach ($_POST as $key => $value)
        {
            $_SESSION[$form_nome][$key] = $value;
        }
    }

    public function getForm($form_nome)
    {
        if(isset($_SESSION[$form_nome]))
        {
            return $_SESSION[$form_nome];
        }
        return false;
    }

    public function addSuccessMessage($message)
    {
        $flashmessages = $this->get('flashmessages');
        if(!$flashmessages)
        {
            $flashmessages = ['error'=>[],'success'=>[]];
        }
        $flashmessages['success'][] = $message;
        $this->set('flashmessages',$flashmessages);
    }

    public function addErrorMessage($message)
    {
        $flashmessages = $this->get('flashmessages');
        if(!$flashmessages)
        {
            $flashmessages = ['error'=>[],'success'=>[]];
        }
        $flashmessages['error'][] = $message;
        $this->set('flashmessages',$flashmessages);
    }

    public function getFlashMessages()
    {
        $flashmessages = $this->get('flashmessages');
        if($flashmessages)
        {
            $return = $flashmessages;
            //azzero i messaggi
            $flashmessages = ['error'=>[],'success'=>[]];
            $this->set('flashmessages', $flashmessages);
            return $return;
        }
        return ['error'=>[],'success'=>[]];
    }

    /**
     * Tear down the session and wipe all its data.     *
     * @return SessionManager
     */
    public function destroy()
    {
        $_SESSION = [];

        session_destroy();
        return $this;
    }

    public function delete($key)
    {
        if(!isset($_SESSION[$this->name][$key]))
        {
            return false;
        }
        unset($_SESSION[$this->name][$key]);
        return $this;
    }
}