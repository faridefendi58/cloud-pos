<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class UserController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/login', [$this, 'login']);
        $app->map(['GET'], '/logout', [$this, 'logout']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['logout'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['login'],
                'users' => ['*'],
            ]
        ];
    }

    public function login($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [];
        if (isset($_POST['username']) && isset($_POST['password'])){
            $username = strtolower($_POST['username']);
            $model = \Model\AdminModel::model()->findByAttributes(['username'=>$username]);
            if ($model instanceof \RedBeanPHP\OODBBean){
                if ($model->status == 0 ) {
                    $result = [
                        'success' => 0,
                        'message' => 'User tidak aktif.',
                    ];
                } else {
                    $has_password = \Model\AdminModel::hasPassword($_POST['password'], $model->salt);
                    if ($model->password == $has_password){
                        $login = $this->_user->login($model, $remember);
                        if ($login){
                            $result = [
                                'success' => 1,
                                'message' => 'Selamat datang '.$model->name,
                                'id' => $model->id,
                                'username' => $model->username,
                                'name' => $model->name
                            ];
                        }
                    } else {
                        $result = [
                            'success' => 0,
                            'message' => 'Password tidak sesuai.',
                        ];
                    }
                }

            }
        }
        
        return $response->withJson($result, 201);
    }

    public function logout($request, $response, $args)
    {
        $result = [];
        if ($this->_user->isGuest()){
            $result = [
                'success' => 0,
                'message' => 'User tidak ditemukan.',
            ];
        }

        $logout = $this->_user->logout();
        if ($logout){
            $result = [
                'success' => 0,
                'message' => 'User telah berhasil logout',
            ];
        }

        return $response->withJson($result, 201);
    }
}