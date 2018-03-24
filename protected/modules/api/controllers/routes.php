<?php
// pos routes
$app->get('/api', function ($request, $response, $args) use ($user) {

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

$app->group('/api', function () use ($user) {
    $this->group('/user', function() use ($user) {
        new Api\Controllers\UserController($this, $user);
    });
    $this->group('/receipt', function() use ($user) {
        new Api\Controllers\ReceiptController($this, $user);
    });
    $this->group('/warehouse', function() use ($user) {
        new Api\Controllers\WarehouseController($this, $user);
    });
});

?>
