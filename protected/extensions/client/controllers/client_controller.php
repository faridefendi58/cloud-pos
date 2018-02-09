<?php

namespace Extensions\Controllers;

use Extensions\Components\ClientBaseController as ClientBaseController;

class ClientController extends ClientBaseController
{
    public function __construct($app, $client)
    {
        parent::__construct($app, $client);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/login', [$this, 'login']);
        $app->map(['GET', 'POST'], '/logout', [$this, 'logout']);
    }

    public function login($request, $response, $args)
    {
        if (!$this->_user->isGuest()){
            return $this->_container->response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Hi '.$this->_user->name.', Anda tidak dapat mengakses halaman ini karena telah memiliki session login.');
        }

        $errors = [];
        if (isset($_POST['Client'])) {
            $client = \ExtensionsModel\ClientModel::model()->findByAttributes( ['email' => $_POST['Client']['email']] );
            if (!$client instanceof \RedBeanPHP\OODBBean) {
                array_push($errors, $_POST['Client']['email'].' tidak terdaftar');
                $success = false;
            }

            $model = new \ExtensionsModel\ClientModel();
            $password = $model->hasPassword($_POST['Client']['password'], $client->salt);
            if ($password == $client->password) {
                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;
                $login = $this->_user->login($client);
                if ($login)
                    return $response->withRedirect('/');
            } else {
                array_push($errors, 'Kata sandi yang Anda masukkan salah');
                $success = false;
            }
        }

        return $this->_container->view->render($response, 'client/login.phtml', [
            'message' => (!empty($message))? $message : null,
            'success' => (!empty($success))? $success : null,
            'errors' => (!empty($errors) && count($errors)>0)? $errors : null,
            'client' => (isset($_POST['Client']))? $_POST['Client'] : null
        ]);
    }

    public function logout($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $this->_container->response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Anda tidak dapat logout karena tidak memiliki session login.');
        }
        $logout = $this->_user->logout();
        if ($logout)
            return $response->withRedirect('/');
    }
}