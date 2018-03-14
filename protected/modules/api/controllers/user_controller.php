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
        $app->map(['POST'], '/register', [$this, 'register_user']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['logout'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['login', 'register'],
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

    public function register_user($request, $response, $args)
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
        if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['email'])){
            $username = strtolower($_POST['username']);
            $model = \Model\AdminModel::model()->findByAttributes(['username'=>$username]);
            if (!$model instanceof \RedBeanPHP\OODBBean){
                $admodel = new \Model\AdminModel();
                $admodel->username = $username;
                $admodel->name = (isset($_POST['name'])) ? $_POST['name'] : $_POST['username'];
                $admodel->email = $_POST['email'];
                $admodel->salt = md5(time());
                $admodel->password = $admodel->hasPassword($_POST['password'], $admodel->salt);
                $admodel->group_id = 2;
                $admodel->status = 0;
                $admodel->created_at = date("Y-m-d H:i:s");
                $save = \Model\AdminModel::model()->save(@$admodel);
                if ($save){
                    $result = [
                        'success' => 1,
                        'message' => 'Data berhasi disimpan.',
                        'id' => $admodel->id,
                        'username' => $admodel->username,
                        'name' => $admodel->name
                    ];
                } else {
                    $result = [
                        'success' => 0,
                        'message' => 'Data gagal disimpan.',
                        'errors' => \Model\AdminModel::model()->getErrors(false, false, false)
                    ];
                }
            } else {
                $result = [
                    'success' => 0,
                    'message' => $username.' sudah pernah terdaftar di sistem.',
                ];
            }
        }

        return $response->withJson($result, 201);
    }
}