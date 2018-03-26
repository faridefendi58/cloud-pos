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
        $app->map(['GET'], '/confirm/[{hash}]', [$this, 'confirm']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['logout'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['login', 'register', 'confirm'],
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
                    $dts = [
                        'password' => $admodel->password,
                        'salt' => $admodel->salt,
                        'name' => $admodel->name,
                        'email' => $admodel->email
                    ];

                    $send_mail_confirmation = $this->_send_confirmation_mail($dts);

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

    public function confirm($request, $response, $args)
    {
        $result = [];
        if ($this->_user->isGuest()){
            $result = [
                'success' => 0,
                'message' => 'User tidak ditemukan.',
            ];
        }

        if (!isset($args['hash'])){
            $result = [
                'success' => 0,
                'message' => 'Konfirmasi gagal.',
            ];
        }

        $arr_hash = explode(".", $args['hash']);
        $md5_pass = $arr_hash[0];
        $salt = $arr_hash[1];
        $model = \Model\AdminModel::model()->findByAttributes(['salt' => $salt]);
        $result = ['success' => 0, 'message' => 'Terjadi kesalahan dalam mengaktifkan user.'];
        if ($model instanceof \RedBeanPHP\OODBBean) {
            if (md5($model->password) != $md5_pass) {
                $result['success'] = 0;
                $result['message'] = 'User tidak ditemukan.';
            } else {
                if ($model->status == 0) {
                    $model->status = 1;
                    $model->updated_at = date("Y-m-d H:i:s");
                    $update = \Model\AdminModel::model()->update($model);
                    if ($update) {
                        $result['success'] = 1;
                        $result['message'] = 'Konfimasi akun berhasil.';
                    }
                } else {
                    $result['message'] = 'User ini sudah dalam kondisi aktif.';
                }
            }
        }

        return $response->withJson($result, 201);
    }

    private function _send_confirmation_mail($data)
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $settings = $this->_settings;
        $url = $settings['params']['site_url'].'/api/user/confirm/'.md5($data['password']).'.'.$data['salt'];

        try {
            //Server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $settings['params']['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['params']['admin_email'];
            $mail->Password = $settings['params']['smtp_secret'];
            $mail->SMTPSecure = $settings['params']['smtp_secure'];
            $mail->Port = $settings['params']['smtp_port'];

            //Recipients
            $mail->setFrom( $settings['params']['admin_email'], 'Admin' );
            $mail->addAddress( $data['email'], $data['name'] );
            $mail->addReplyTo( $settings['params']['admin_email'], 'Admin' );

            //Content
            $mail->isHTML(true);
            $mail->Subject = '['.$settings['params']['site_name'].'] Konfimasi Pendaftaran akun';
            $mail->Body = "Halo ".$data['name'].", 
	        <br/><br/>
            Silakan klik url berikut untuk mengaktifkan akun Anda :
            <br/><br/>
            <a href='".$url."' target='_blank'>".$url."</a>";

            $mail->send();
        } catch (Exception $e) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $mail->ErrorInfo;
            exit;
        }

        return true;
    }
}