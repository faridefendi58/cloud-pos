<?php

namespace Extensions\Components;

class ClientIdentity
{
    public $session_id;
    public $id;
    public $name;
    public $email;

    public function __construct($app)
    {
        $this->session_id = md5('client_'.$app->getContainer()->get('settings')['name']);
        $this->id = $this->getId();
        $this->name = $this->getName();
        $this->email = $this->getEmail();
    }

    public function isGuest()
    {
        if (!isset($_SESSION[$this->session_id]['client'])){
            return true;
        }

        return false;
    }

    public function login($model)
    {
        $_SESSION[$this->session_id]['client'] = [
            'id' => $model->id,
            'email' => $model->email,
            'name' => $model->name,
            'group_id' => $model->client_group_id
        ];

        return true;
    }

    public function logout()
    {
        $_SESSION[$this->session_id] = null;
        return true;
    }

    public function getId()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['client']['id'];
        }

        return null;
    }

    public function getName()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['client']['name'];
        }

        return null;
    }

    public function getEmail()
    {
        if (!empty($_SESSION[$this->session_id])){
            return $_SESSION[$this->session_id]['client']['email'];
        }

        return null;
    }
}