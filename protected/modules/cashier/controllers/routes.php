<?php
// pos routes
$app->get('/cashier', function ($request, $response, $args) use ($user) {
	if ($user->isGuest()){
        return $response->withRedirect('/cashier/default/login');
    }

	return $this->module->render($response, 'default/index.html', [
        'name' => $args['name'],
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

$app->group('/cashier', function () use ($user) {
    $this->group('/default', function() use ($user) {
        new Cashier\Controllers\DefaultController($this, $user);
    });
    $this->group('/sale', function() use ($user) {
        new Cashier\Controllers\SaleController($this, $user);
    });
});

?>
