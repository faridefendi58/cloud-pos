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
    $this->group('/stock', function() use ($user) {
        new Api\Controllers\StockController($this, $user);
    });
    $this->group('/product', function() use ($user) {
        new Api\Controllers\ProductController($this, $user);
    });
    $this->group('/transfer', function() use ($user) {
        new Api\Controllers\TransferController($this, $user);
    });
    $this->group('/purchase', function() use ($user) {
        new Api\Controllers\PurchaseController($this, $user);
    });
    $this->group('/supplier', function() use ($user) {
        new Api\Controllers\SupplierController($this, $user);
    });
    $this->group('/shipment', function() use ($user) {
        new Api\Controllers\ShipmentController($this, $user);
    });
    $this->group('/notification', function() use ($user) {
        new Api\Controllers\NotificationController($this, $user);
    });
    $this->group('/delivery', function() use ($user) {
        new Api\Controllers\DeliveryController($this, $user);
    });
    $this->group('/inventory', function() use ($user) {
        new Api\Controllers\InventoryController($this, $user);
    });
    $this->group('/transaction', function() use ($user) {
        new Api\Controllers\TransactionController($this, $user);
    });
	$this->group('/customer', function() use ($user) {
        new Api\Controllers\CustomerController($this, $user);
    });
});

?>
