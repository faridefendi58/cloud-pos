<?php
// panel-admin routes
$app->get('/panel-admin', function ($request, $response, $args) use ($user) {
	if ($user->isGuest()){
        return $response->withRedirect('/panel-admin/default/login');
    }

    $vmodel = new \Model\VisitorModel();
    $params = [];
    if (isset($_GET['start']) || isset($_GET['end'])) {
        $params = [
            'date_from' => date("Y-m-d", $_GET['start'] / 1000),
            'date_to' => date("Y-m-d", $_GET['end'] / 1000),
        ];
    }

	return $this->module->render($response, 'default/index.html', [
        'name' => $args['name'],
        'vmodel' => $vmodel,
        'params' => $params
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

$app->group('/panel-admin', function () use ($user) {
    $this->group('/default', function() use ($user) {
        new PanelAdmin\Controllers\DefaultController($this, $user);
    });
    $this->group('/pages', function() use ($user) {
        new PanelAdmin\Controllers\PagesController($this, $user);
    });
    $this->group('/themes', function() use ($user) {
        new PanelAdmin\Controllers\ThemesController($this, $user);
    });
    $this->group('/users', function() use ($user) {
        new PanelAdmin\Controllers\UsersController($this, $user);
    });
    $this->group('/params', function() use ($user) {
        new PanelAdmin\Controllers\ParamsController($this, $user);
    });
    $this->group('/extensions', function() use ($user) {
        new PanelAdmin\Controllers\ExtensionsController($this, $user);
    });
});

?>
