<?php

namespace Auth\Model;

class User
{
    public $id = 0;
    public $username = 'guest';
    public $email;
    public $id_role;

    public function __construct()
    {
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    public function setId_role($id_role)
    {
        $this->id_role = $id_role;
        return $this;
    }
}