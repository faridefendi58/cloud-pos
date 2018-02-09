<?php
// Fetch DI Container
$container = $app->getContainer();

// User identity
require __DIR__ . '/identity.php';
$user = new \Components\UserIdentity($app);

// Check if there is another identity
$client = null;
if (!empty($settings['settings']['params']['extensions'])) {
    $exts = json_decode($settings['settings']['params']['extensions'], true);
    if (is_array($exts) && in_array('client', $exts)) {
        $client = new \Extensions\Components\ClientIdentity($app);
    }
}

// Controller
require __DIR__ . '/controller.php';

// Tool
require __DIR__ . '/tool.php';

//trailling slash
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        }
        else {
            return $next($request->withUri($uri), $response);
        }
    }

    return $next($request, $response);
});

// Register Twig View helper
$container['view'] = function ($c) use ($client) {
	$settings = $c->get('settings');

	$view_path = $settings['theme']['path'] . '/' . $settings['theme']['name'] . '/views';
    $view = new \Slim\Views\Twig( $view_path , [
        'cache' => $settings['cache']['path'],
        'auto_reload' => true,
    ]);

    addFilter($view->getEnvironment(), $c);
    addGlobal($view->getEnvironment(), $c, $client);

    return $view;
};

// Register Twig View module
$container['module'] = function ($c) use ($user) {
	$settings = $c->get('settings');
    $uri_path = $c->get('request')->getUri()->getPath();
    $view_path = $settings['admin']['path'] . '/views';

    if (!empty($uri_path)) { // allow each module to have own theme view
        $chunk = explode("/", $uri_path);
        $mod_paths = $settings['basePath'].'/modules/'.$chunk[1].'/views';
        if (is_dir($mod_paths))
            $view_path = $mod_paths;
    }

    $view = new \Slim\Views\Twig( $view_path , [
        'cache' => $settings['cache']['path'],
        'auto_reload' => true,
    ]);

    addFilter($view->getEnvironment(), $c);
    addGlobal($view->getEnvironment(), $c, $user);

    return $view;
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// filter
function addFilter($env, $c)
{
    $uri = $c['request']->getUri();
    $base_url = $uri->getScheme().'://'.$uri->getHost().$uri->getBasePath();
    if (!empty($uri->getPort()))
        $base_url .= ':'.$uri->getPort();

    $admin_module = $c->get('settings')['admin']['name'];
    $theme = $c->get('settings')['theme']['name'];

    $filters = [
        new \Twig_SimpleFilter('dump', function ($string) {
            return var_dump($string);
        }),
        new \Twig_SimpleFilter('link', function ($string) use ($base_url) {
            return $base_url .'/'. $string;
        }),
        new \Twig_SimpleFilter('asset_url', function ($string) use ($base_url, $theme){
            return $base_url .'/../themes/'. $theme .'/assets/'. $string;
        }),
        new \Twig_SimpleFilter('admin_asset_url', function ($string) use ($base_url, $admin_module) {
            return $base_url .'/protected/modules/'. $admin_module .'/assets/'. $string;
        }),
        new \Twig_SimpleFilter('alink', function ($string) use ($base_url, $admin_module) {
            return $base_url .'/'. $admin_module. '/' .$string;
        }),
        new \Twig_SimpleFilter('json_decode', function ($string) {
            return json_decode($string, true);
        }),
        new \Twig_SimpleFilter('truncate', function ($string, $length = 30) {
            if (strlen($string) > $length) {
                if (false !== ($breakpoint = strpos($string, ' ', $length))) {
                    $length = $breakpoint;
                }

                return substr($string, 0, $length) . ' ...';
            }
        }),
    ];

    $uri_path = $c->get('request')->getUri()->getPath();
    if (!empty($uri_path)) { // allow each module to have own filter
        $chunk = explode("/", $uri_path);
        $mod_paths = $c->get('settings')['basePath'].'/modules/'.$chunk[1];
        if (is_dir($mod_paths)) {
            $module_name = $chunk[1];
            $mfilters = [
                new \Twig_SimpleFilter('m_asset_url', function ($string) use ($base_url, $module_name) {
                    return $base_url .'/protected/modules/'. $module_name .'/assets/'. $string;
                }),
                new \Twig_SimpleFilter('mlink', function ($string) use ($base_url, $module_name) {
                    return $base_url .'/'. $module_name. '/' .$string;
                })
            ];
            $filters = array_merge($filters, $mfilters);
        }
    }

    foreach ($filters as $i => $filter) {
        $env->addFilter($filter);
    }
}

// global variable
function addGlobal($env, $c, $user = null)
{
    $uri = $c['request']->getUri();
    $setting = $c->get('settings');
    $base_url = $uri->getScheme().'://'.$uri->getHost().$uri->getBasePath();
    if (!empty($uri->getPort()))
        $base_url .= ':'.$uri->getPort();
    $base_path = $uri->getScheme().'://'.$uri->getHost().$uri->getPath();
    if (!empty($uri->getPort()))
        $base_path = $base_url.$uri->getPath();

    $globals = [
        'name' => $setting['name'],
        'baseUrl' => (!defined('BASE_URL')) ? $base_url : BASE_URL,
        'basePath' => $setting['basePath'],
        'adminBasePath' => $setting['admin']['path'],
        'user' => $user,
        'params' => $setting['params'],
        'optionModel' => new \Model\OptionsModel(),
        'request' => $c['request'],
        'currentPath' => $base_path,
        'tool' => new \Components\Tool($setting['theme']['path'].'/'.$setting['theme']['name'].'/')
    ];

    $env->addGlobal('App', $globals);
}
