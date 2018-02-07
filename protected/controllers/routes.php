<?php
// Modules Routes
foreach(glob($settings['settings']['basePath'] . '/modules/*/controllers/routes.php') as $mod_routes) {
    require_once $mod_routes;
}

// Extensions routes
foreach(glob($settings['settings']['basePath'] . '/extensions/*/controllers/routes.php') as $ext_routes) {
    require_once $ext_routes;
}

$app->get('/niagahoster', function ($request, $response, $args) {
    return $response->withRedirect( 'https://goo.gl/V3dpJU' );
});

$app->get('/[{name}]', function ($request, $response, $args) {
    
	if (empty($args['name']))
		$args['name'] = 'index';

    $settings = $this->get('settings');
    if (!file_exists($settings['theme']['path'].'/'.$settings['theme']['name'].'/views/'.$args['name'].'.phtml')) {
        return $this->response
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Page not found!');
    }

    $exts = json_decode( $settings['params']['extensions'], true );
    $mpost = null;
    if (in_array( 'blog', $exts )) {
        $mpost = new \ExtensionsModel\PostModel();
    }

    if (isset($_GET['e']) && $_GET['e'] > 0) { // editing procedure
        $view_path = $settings['theme']['path'] . '/' . $settings['theme']['name'] . '/views';
        if (file_exists($view_path.'/'.$args['name'] . '.phtml')) {
            if (file_exists($view_path.'/staging/'.$args['name'] . '.ehtml')) {
                unlink($view_path.'/staging/'.$args['name'] . '.ehtml');
            }
            $cp = copy($view_path.'/'.$args['name'] . '.phtml', $view_path.'/staging/'.$args['name'] . '.ehtml');
            if ($cp) {
                $content = file_get_contents($view_path.'/staging/'.$args['name'] . '.ehtml');
                $parsed_content = str_replace(array("{{", "}}"), array("[[", "]]"), $content);

                $update = file_put_contents($view_path.'/staging/'.$args['name'] . '.ehtml', $parsed_content);
            }

            return $this->view->render($response, 'staging/' . $args['name'] . '.ehtml', [
                'name' => $args['name'],
                'mpost' => $mpost,
                'request' => $_GET
            ]);
        }
    }

    return $this->view->render($response, $args['name'] . '.phtml', [
        'name' => $args['name'],
        'request' => $_GET,
        'mpost' => $mpost
    ]);
});

$app->post('/kontak-kami', function ($request, $response, $args) {
    $message = 'Pesan Anda gagal dikirimkan.';
    $settings = $this->get('settings');
    if (isset($_POST['Contact'])){
        //send mail to admin
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
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
            $mail->setFrom( $settings['params']['admin_email'], 'Admin slightSite' );
            $mail->addAddress( $settings['params']['admin_email'], 'Farid Efendi' );
            $mail->addReplyTo( $_POST['Contact']['email'], $_POST['Contact']['name'] );

            //Content
            $mail->isHTML(true);
            $mail->Subject = '[slightSite] Kontak Kami';
            $mail->Body = "Halo Admin, 
	        <br/><br/>
            Ada pesan baru dari pengunjung dengan data berikut:
            <br/><br/>
            <b>Judul pesan</b> : ".$_POST['Contact']['subject']." <br/>
            <b>Nama pengunjung</b> : ".$_POST['Contact']['name']." <br/> 
            <b>Alamat Email</b> : ".$_POST['Contact']['email']." <br/>
            <br/>
            <b>Isi Pesan</b> :<br/> ".$_POST['Contact']['message']."";

            $mail->send();
        } catch (Exception $e) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $mail->ErrorInfo;
            exit;
        }

        $message = 'Pesan Anda berhasil dikirim. Kami akan segera merespon pesan Anda.';
    }

    echo $message; exit;
});

$app->post('/tracking', function ($request, $response, $args) {
    if (isset($_POST['s'])){
        $model = new \Model\VisitorModel('create');
        $model->client_id = 0;
        if(!empty($_POST['s'])){
            $model->session_id = $model->getCookie('_ma',false);
            if (!empty($model->cookie)){
                $model->date_expired = $model->cookie;
            } else {
                //Yii::app()->request->cookies->remove('_ma');
                $model->date_expired = date("Y-m-d H:i:s",time()+1800);
            }
        }
        $model->ip_address = $_SERVER['REMOTE_ADDR'];
        $model->page_title = $_POST['t'];
        $model->url = $_POST['u'];
        $model->url_referrer = $_POST['r'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->platform = $_POST['p'];
        $model->user_agent = $_POST['b'];

        require_once $this->settings['basePath'] . '/components/mobile_detect.php';
        $mobile_detect = new \Components\MobileDetect();
        $model->mobile = ($mobile_detect->isMobile())? 1 : 0;

        $create = \Model\VisitorModel::model()->save(@$model);

        if ($create > 0) {
            if ($model->session_id == 'false' || empty($model->session_id)) {
                $model2 = \Model\VisitorModel::model()->findByPk($model->id);
                $model2->session_id = md5($create);
                $update = \Model\VisitorModel::model()->update(@$model2);
                //$cookie_time = (3600 * 0.5); // 30 minute
                //setcookie("ma_session", $model->session_id, time() + $cookie_time, '/');
            }
            //set notaktif
            $model->deactivate($model->session_id);
            // update the current record
            if (!is_object($model2))
                $model2 = \Model\VisitorModel::model()->findByPk($model->id);
            $model2->active = 1;
            $update2 = \Model\VisitorModel::model()->update($model2);

            echo $model2->session_id;
        }else{
            echo 'failed';
        }

        exit;
    }
});
