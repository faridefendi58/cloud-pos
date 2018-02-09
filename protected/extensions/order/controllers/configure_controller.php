<?php

namespace Extensions\Controllers;

use Extensions\Components\ClientBaseController as ClientBaseController;

class ConfigureController extends ClientBaseController
{
    public function __construct($app, $client)
    {
        parent::__construct($app, $client);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/signup', [$this, 'signup']);
        $app->map(['POST'], '/theme', [$this, 'theme']);
        $app->map(['GET'], '/build', [$this, 'build']);
        $app->map(['GET', 'POST'], '/[{name}]', [$this, 'configure']);
    }

    public function configure($request, $response, $args)
    {
        $model = new \ExtensionsModel\ClientOrderModel('create');
        $product = \ExtensionsModel\ProductModel::model()->findByAttributes( ['slug'=>$args['name']] );
        if (!$product instanceof \RedBeanPHP\OODBBean) {
            return $this->_container->response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Page not found!');
        }

        return $this->_container->view->render($response, 'order/configure_site.phtml', [
            'model' => $model,
            'product' => $product
        ]);
    }

    public function signup($request, $response, $args)
    {
        $model = new \ExtensionsModel\ClientOrderModel('create');

        $errors = [];
        $product_slug = $_POST['Order']['slug'];
        $theme = $_POST['Order']['theme'];
        
        if (isset($_POST['Client'])) {

            $product_slug = $_POST['Client']['product_slug'];
            $theme = $_POST['Client']['theme'];

            if (($this->_settings['params']['re_captcha_verification'] > 0) && empty($_POST['g-recaptcha-response'])) {
                return $this->_container->response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'text/html')
                    ->write('Page not found!');
            }

            if ($this->_settings['params']['re_captcha_verification'] > 0)
                $verify = $this->siteverify($_POST['g-recaptcha-response']);
            else
                $verify = true;

            if ($verify) {
                if ($_POST['Client']['login'] == 0) {
                    $client = new \ExtensionsModel\ClientModel('create');
                    $client->email = $_POST['Client']['email'];
                    $client->name = $_POST['Client']['name'];
                    $client->salt = md5(uniqid());
                    $client->password = $client->hasPassword($_POST['Client']['password'], $client->salt);
                    $client->status = 'active';
                    $client->client_group_id = 1;
                    $client->created_at = date("Y-m-d H:i:s");
                    $client->updated_at = date("Y-m-d H:i:s");
                    $save = \ExtensionsModel\ClientModel::model()->save(@$client);
                    if ($save) {
                        $message = 'Data Anda telah berhasil disimpan.';
                        $success = true;
                        $login = $this->_user->login($client);
                        if ($login)
                            return $response->withRedirect('/order/configure/'.$_GET['p']);
                    } else {
                        $message = \ExtensionsModel\ClientModel::model()->getErrors(false);
                        $errors = \ExtensionsModel\ClientModel::model()->getErrors(true, true);
                        $success = false;
                    }
                } else {
                    $client = \ExtensionsModel\ClientModel::model()->findByAttributes( ['email' => $_POST['Client']['email']] );
                    $success = false;
                    if (!$client instanceof \RedBeanPHP\OODBBean) {
                        array_push($errors, $_POST['Client']['email'].' tidak terdaftar');
                        $success = false;
                    }

                    $model = new \ExtensionsModel\ClientModel();
                    $password = $model->hasPassword($_POST['Client']['password'], $client->salt);
                    if ($password == $client->password) {
                        $success = true;
                        $login = $this->_user->login($client);
                        if ($login) {
                            $params = [
                                'p' => $product_slug,
                                't' => $theme,
                                's' => $_POST['Client']['site_name'],
                                'h' => md5($product_slug.''.$theme.''.$_POST['Client']['site_name'])
                            ];
                            $query = http_build_query( $params );
                            $_SESSION['h'] = $params['h'];

                            return $response->withRedirect('/order/configure/build?'.$query);
                        }

                    } else {
                        array_push($errors, 'Kata sandi yang Anda masukkan salah');
                        $success = false;
                    }
                }
            }

        }

        return $this->_container->view->render($response, 'order/signup.phtml', [
            'model' => $model,
            'message' => (!empty($message))? $message : null,
            'success' => (!empty($success))? $success : null,
            'errors' => (count($errors)>0)? $errors : null,
            'client' => (!empty($_POST['Client']))? $_POST['Client'] : null,
            'product_slug' => $product_slug, 
            'theme' => $theme,
        ]);
    }

    public function theme($request, $response, $args)
    {
        if (isset($_POST['Order'])) {
            $data = $_POST['Order'];
            $product = \ExtensionsModel\ProductModel::model()->findByAttributes( ['slug'=>$_POST['Order']['slug']] );

            return $this->_container->view->render($response, 'order/themes.phtml', [
                'data' => $data,
                'product' => $product
            ]);
        } else {
            return $response->withRedirect('/');
        }
    }

    public function build($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect('/order/configure/'.$args['slug']);
        }

        if (isset($_GET['p']) && isset($_GET['t']) && isset($_GET['s']) && isset($_GET['h'])) {
            $product = \ExtensionsModel\ProductModel::model()->findByAttributes( ['slug'=>$_GET['p']] );
            $hash = md5($_GET['p'].$_GET['t'].$_GET['s']);

            if ($_GET['h'] == $hash && isset($_SESSION['h'])) {

                $model = new \ExtensionsModel\ClientOrderModel('create');
                $model->client_id = $this->_user->id;
                $model->product_id = $product->id;
                $model->group_id = time();
                $model->group_master = 1;
                $model->invoice_option = \ExtensionsModel\ClientOrderModel::INVOICE_OPTION_NO_INVOICE;
                $model->title = $product->title.' untuk '.$_GET['s'];
                $model->currency = 'IDR';
                $model->service_type = $product->type;
                $model->period = '1M';
                $model->quantity = 1;
                $model->unit = 'product';
                $model->price = 0;
                $model->discount = 0;
                $model->status = \ExtensionsModel\ClientOrderModel::STATUS_PENDING_SETUP;
                $model->config = json_encode($_GET);
                $model->created_at = date('Y-m-d H:i:s');
                $model->updated_at = date('Y-m-d H:i:s');
                $save = \ExtensionsModel\ClientOrderModel::model()->save(@$model);
                if ($save) {
                    $_SESSION['h'] = null;
                    // start to build
                    $service = new \Extensions\OrderService($this->_settings);
                    if (isset($model->id) && !$model instanceof \RedBeanPHP\OODBBean) {
                        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk( $model->id );
                    }

                    if (empty($model->service_id)) {
                        $activate = $service->activate($model);
                        if ($activate) {
                            $omodel = new \ExtensionsModel\ClientOrderModel();
                            $service = $omodel->get_service( $model->id );
                        }
                    } else {
                        $service = $omodel->get_service( $model->id );
                    }

                    if (!empty($service['domain'])) {
                        $preview_params = [
                            'domain' => $service['domain'],
                            'site_name' => $_GET['s'],
                            'theme' => $_GET['t']
                        ];
                        $preview_url = $this->preview_url( $preview_params );
                    }
                }

                return $this->_container->view->render($response, 'order/build.phtml', [
                    'model' => $model,
                    'product' => $product,
                    'preview_url' => $preview_url
                ]);
            } else { // just if we got an accidance on installation
                $omodel = new \ExtensionsModel\ClientOrderModel();
                $model = $omodel->find_order_by_hash($_GET['h']);

                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $service = $omodel->get_service( $model->id );
                    if (!empty($service['domain'])) {
                        $preview_params = [
                            'domain' => $service['domain'],
                            'site_name' => $_GET['s'],
                            'theme' => $_GET['t']
                        ];
                        $preview_url = $this->preview_url( $preview_params );
                    }

                    if (empty($preview_url)) {
                        return $this->_container->response
                            ->withStatus(500)
                            ->withHeader('Content-Type', 'text/html')
                            ->write('Preview url not found. Please resignup!');
                    }

                    return $this->_container->view->render($response, 'order/build.phtml', [
                        'model' => $model,
                        'product' => $product,
                        'preview_url' => $preview_url
                    ]);
                }
            }
        }

        return $this->_container->response
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Page not found!');
    }

    protected function siteverify($response)
    {
        $data = array(
            'secret' => $this->_settings['params']['re_captcha_secret'],
            'response' => $response
        );

        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($verify);
        $response = json_decode($response);

        return $response->success;
    }

    private function preview_url($data)
    {
        $domain = $data['domain'];
        $pecah = explode( ".", $domain );
        $params = [
            'd' => 'admin_'.$pecah[0].'d',
            'u' => 'admin_'.$pecah[0].'u',
            'p' => $pecah[0].'123'
        ];
        if (!empty($data['site_name']))
            $params['s'] = $data['site_name'];

        if (!empty($data['theme']))
            $params['t'] = $data['theme'];

        $params['h'] = md5($params['d'].''.$params['u'].''.$params['p']);

        $query = http_build_query( $params );

        $preview_url = 'http://'.$domain.'/install.php?'.$query;
        return $preview_url;
    }
}