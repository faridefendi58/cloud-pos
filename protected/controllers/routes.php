<?php
// Modules Routes
foreach(glob($settings['settings']['basePath'] . '/modules/*/controllers/routes.php') as $mod_routes) {
    require_once $mod_routes;
}

// Extensions routes
foreach(glob($settings['settings']['basePath'] . '/extensions/*/controllers/routes.php') as $ext_routes) {
    require_once $ext_routes;
}

$app->get('/[{name}]', function ($request, $response, $args) {
    
	if (empty($args['name']))
		$args['name'] = 'index';

    // just redirect to pos due to no homepage
    if ($args['name'] == 'index') {
        return $this->response->withRedirect( 'pos' );
    }

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
