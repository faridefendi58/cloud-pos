<?php

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

$app->group('/order', function () use ($client, $user) {
    $this->group('/configure', function() use ($client) {
        new Extensions\Controllers\ConfigureController($this, $client);
    });
    $this->group('/admin', function() use ($user) {
        new Extensions\Controllers\AdminOrderController($this, $user);
    });
});

?>
