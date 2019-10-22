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
        $app->map(['POST'], '/update', [$this, 'update']);
        $app->map(['POST'], '/change-password', [$this, 'change_password']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['logout'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['login', 'register', 'confirm', 'update', 'change-password'],
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
                        $roles = $this->_get_roles($model->id);
                        $pics = $this->_get_coverage_wh($model->id, $model->name);
                        $login = $this->_user->login($model, false);
                        if ($login){
                            $result = [
                                'success' => 1,
                                'message' => 'Selamat datang '.$model->name,
                                'id' => $model->id,
                                'username' => $model->username,
                                'name' => $model->name,
                                'is_admin' => ($model->group_id == 1)? true : false,
                                'is_pic' => (count($pics) > 0)? true : false,
                                'roles' => $roles,
                                'coverage' => $pics,
                                'email' => $model->email,
                                'phone' => (!empty($model->phone))? $model->phone : '-',
								'group_id' => $model->group_id
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
                if (isset($_POST['group_id'])) {
                    $admodel->group_id = $_POST['group_id'];
                }

                $admodel->status = 0;
                if (isset($_POST['status'])) {
                    $admodel->status = $_POST['status'];
                }
                $admodel->created_at = date("Y-m-d H:i:s");
                $save = \Model\AdminModel::model()->save(@$admodel);
                if ($save){
                    $dts = [
                        'password' => $admodel->password,
                        'salt' => $admodel->salt,
                        'name' => $admodel->name,
                        'email' => $admodel->email
                    ];

                    if (!isset($_POST['status'])) {
                        try {
                            $send_mail_confirmation = $this->_send_confirmation_mail($dts);
                        } catch (\Exception $exception) {

                        }
                    }

					if (isset($_POST['warehouse_id'])) {
						$whs_model = new \Model\WarehouseStaffsModel();
						$whs_model->warehouse_id = $_POST['warehouse_id'];
						$whs_model->role_id = $admodel->group_id;
						$whs_model->admin_id = $admodel->id;
						$whs_model->created_at = date("Y-m-d H:i:s");
						$whs_model->created_by = $admodel->id;
						$save2 = \Model\WarehouseStaffsModel::model()->save(@$whs_model);
					}

                    $result = [
                        'success' => 1,
                        'message' => 'Data berhasi disimpan.',
                        'id' => $admodel->id,
                        'username' => $admodel->username,
                        'name' => $admodel->name,
						'group_id' => $admodel->group_id
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
                        $result['message'] = 'Konfirmasi akun berhasil.';
                    } else {
                        $result = [
                            'success' => 0,
                            'message' => 'Data gagal disimpan.',
                            'errors' => \Model\AdminModel::model()->getErrors(false, false, false)
                        ];
                    }
                } else {
                    $result['message'] = 'User ini sudah dalam kondisi aktif.';
                }
            }
        } else {
            $result['message'] = 'User tidak ditemukan.';
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

    /**
     * @param $admin_id
     * @return array|bool
     */
    private function _get_roles($admin_id)
    {
        $whs_model = new \Model\WarehouseStaffsModel();
        $items = $whs_model->getData(['admin_id' => $admin_id]);

		$roles = [];
        if (count($items) > 0) {
            foreach ($items as $i => $item) {
                $roles[$item['warehouse_id']] = [
                    'warehouse_name' => $item['warehouse_name'],
                    'warehouse_group_name' => $item['warehouse_group_name'],
                    'warehouse_pic' => (!empty($item['warehouse_group_pic']))? json_decode($item['warehouse_group_pic'], true) : null,
                    'role_name' => $item['role_name'],
                    'roles' => (!empty($item['roles']))? json_decode($item['roles'], true) : null,
                ];
            }

            return $roles;
        } else {
			$roles[8] = [
                    'warehouse_name' => 'Jogja Jl. Kabupaten',
                    'warehouse_group_name' => 'Jogja'
                ];
		}

        return $roles;
    }

    private function _get_coverage_wh($admin_id, $admin_name = null)
    {
        if (empty($admin_name)) {
            $amodel = \Model\AdminModel::model()->findByPk($admin_id);
            if (!$amodel instanceof \RedBeanPHP\OODBBean) {
                return false;
            } else {
                $admin_name = $amodel->name;
            }
        }

        $model = new \Model\WarehouseGroupsModel();
        $items = $model->getDataByPic(['admin_id' => $admin_id, 'admin_name' => $admin_name]);

        $groups = [];
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $i => $item) {
                $groups[$item['id']] = $item['title'];
            }
        }

        return $groups;
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [ 'success' => 0 ];
        $params = $request->getParams();
        if (isset($params['admin_id'])) {
            $model = \Model\AdminModel::model()->findByPk( $params['admin_id'] );
            if ($model instanceof \RedBeanPHP\OODBBean) {
                if (isset($params['username'])) {
                    $model->username = $params['username'];
                }
                if (isset($params['name'])) {
                    $model->name = $params['name'];
                }
                if (isset($params['email'])) {
                    $model->email = $params['email'];
                }
                if (isset($params['phone'])) {
                    $model->phone = $params['phone'];
                }
                $model->updated_at = date("Y-m-d H:i:s");
                $save = \Model\AdminModel::model()->update(@$model);
                if ($save) {
                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasil disimpan.'
                    ];
                }
            } else {
                $result['message'] = 'User tidak ditemukan.';
            }
        }

        return $response->withJson($result, 201);
    }

    public function change_password($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [ 'success' => 0 ];
        $params = $request->getParams();
        if (isset($params['admin_id'])
            && isset($params['old_password'])
            && isset($params['new_password'])) {

            $model = \Model\AdminModel::model()->findByPk( $params['admin_id'] );
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $has_password = \Model\AdminModel::hasPassword($params['old_password'], $model->salt);
                if ($has_password != $model->password) {
                    $result['message'] = 'Password lama yang Anda masukkan salah.';
                } else {
                    $has_password_new = \Model\AdminModel::hasPassword($params['new_password'], $model->salt);
                    $model->password = $has_password_new;
                    $model->updated_at = date("Y-m-d H:i:s");
                    $save = \Model\AdminModel::model()->update(@$model);
                    if ($save) {
                        $result = [
                            "success" => 1,
                            "id" => $model->id,
                            'message' => 'Password berhasil diubah.'
                        ];
                    } else {
                        $result['message'] = 'Password gagal diubah.';
                    }
                }
            } else {
                $result['message'] = 'User tidak ditemukan.';
            }
        } else {
            $result['message'] = 'Silakan masukkan password lama dan baru Anda.';
        }

        return $response->withJson($result, 201);
    }
}
