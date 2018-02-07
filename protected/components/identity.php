<?php

namespace Components;

class UserIdentity
{
    public $session_id;
    public $id;
    public $name;
    
    public function __construct($app)
    {
        $this->session_id = md5($app->getContainer()->get('settings')['name']);
        $this->id = $this->getId();
        $this->name = $this->getName();
    }
    
    public function isGuest()
    {
        if (!isset($_SESSION[$this->session_id])){
            return true;
        }

        return false;
    }

    public function login($model)
    {
        $_SESSION[$this->session_id]['user'] = [
            'id' => $model->id,
            'username' => $model->username,
            'email' => $model->email,
            'group_id' => $model->group_id
        ];

        return true;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        return true;
    }

    public function getId()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['user']['id'];
        }

        return null;
    }

    public function getName()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['user']['username'];
        }

        return null;
    }
}