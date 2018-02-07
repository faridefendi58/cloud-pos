<?php
// frontend url
$app->get('/blog', function ($request, $response, $args) {
    $model = new \ExtensionsModel\PostModel();

    return $this->view->render($response, 'blog.phtml', [
        'name' => $args['name'],
        'mpost' => $model
    ]);
});
$app->get('/blog/[{name}]', function ($request, $response, $args) {

    if (empty($args['name']))
        $args['name'] = 'index';

    $theme = $this->settings['theme'];
    $model = new \ExtensionsModel\PostModel();
    if (!file_exists($theme['path'].'/'.$theme['name'].'/views/'.$args['name'].'.phtml')) {
        $data = $model->getPost($args['name']);

        if (empty($data['id'])) {
            $category = $model->getCategory(['slug' => $args['name']]);
            if (is_array($category) && !empty($category['id'])) {

                return $this->view->render($response, 'blog.phtml', [
                    'category' => $category,
                    'mpost' => $model
                ]);
            }
            
            return $this->response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Page not found!');
        }

        return $this->view->render($response, 'post.phtml', [
            'data' => $data,
            'mpost' => $model
        ]);
    }

    return $this->view->render($response, $args['name'] . '.phtml', [
        'name' => $args['name'],
        'mpost' => $model
    ]);
});

foreach (glob(__DIR__.'/*_controller.php') as $controller) {
	$cname = basename($controller, '.php');
	if (!empty($cname)) {
		require_once $controller;
	}
}

foreach (glob(__DIR__.'/../components/*.php') as $component) {
    $cname = basename($component, '.php');
    if (!empty($cname)) {
        require_once $component;
    }
}

$app->group('/blog', function () use ($user) {
    $this->group('/posts', function() use ($user) {
        new Extensions\Controllers\PostsController($this, $user);
    });
});

?>
