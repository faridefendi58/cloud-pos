<?php

namespace Components;

class UserIdentity
{
    public $session_id;
    public $id;
    public $name;
    public $language;

    public function __construct($app)
    {
        $this->session_id = md5($app->getContainer()->get('settings')['name']);
        $this->id = $this->getId();
        $this->name = $this->getName();
        $this->language = $this->getLanguage();
    }
    
    public function isGuest()
    {
        if (!isset($_SESSION[$this->session_id])){
            if (isset($_COOKIE[$this->session_id])) {
                // renew session if there is remember cookie
                $_SESSION[$this->session_id] = json_decode($_COOKIE[$this->session_id], true);
                return false;
            }
            return true;
        }

        return false;
    }

    public function login($model, $remember = false)
    {
        $_SESSION[$this->session_id]['user'] = [
            'id' => $model->id,
            'username' => $model->username,
            'email' => $model->email,
            'group_id' => $model->group_id,
            'language' => $model->language
        ];

        if ($remember) {
            //remember for 1 month
            setcookie( $this->session_id, json_encode($_SESSION[$this->session_id]), strtotime("+1 month", time()), "/");
        }

        return true;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        if (isset($_COOKIE[$this->session_id])) {
            setcookie( $this->session_id, null, -1, "/" );
        }
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

    public function getLanguage()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['user']['language'];
        }

        return null;
    }
}