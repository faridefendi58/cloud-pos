<?php
// pos routes
$app->get('/pos', function ($request, $response, $args) use ($user) {
	if ($user->isGuest()){
        return $response->withRedirect('/pos/default/login');
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

$app->group('/pos', function () use ($user) {
    $this->group('/default', function() use ($user) {
        new Pos\Controllers\DefaultController($this, $user);
    });
    $this->group('/users', function() use ($user) {
        new Pos\Controllers\UsersController($this, $user);
    });
    $this->group('/params', function() use ($user) {
        new Pos\Controllers\ParamsController($this, $user);
    });
    $this->group('/extensions', function() use ($user) {
        new Pos\Controllers\ExtensionsController($this, $user);
    });
    $this->group('/products', function() use ($user) {
        new Pos\Controllers\ProductsController($this, $user);
    });
    $this->group('/transactions', function() use ($user) {
        new Pos\Controllers\TransactionsController($this, $user);
    });
    $this->group('/suppliers', function() use ($user) {
        new Pos\Controllers\SuppliersController($this, $user);
    });
    $this->group('/warehouses', function() use ($user) {
        new Pos\Controllers\WarehousesController($this, $user);
    });
    $this->group('/purchases', function() use ($user) {
        new Pos\Controllers\PurchasesController($this, $user);
    });
});

?>
