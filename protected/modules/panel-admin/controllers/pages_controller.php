<?php

namespace PanelAdmin\Controllers;

use Components\BaseController as BaseController;
use Prophecy\Exception\Exception;

class PagesController extends BaseController
{
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{name}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{name}]', [$this, 'delete']);
        $app->map(['GET', 'POST'], '/update-visual', [$this, 'update_visual']);
        $app->map(['GET', 'POST'], '/inspiration/[{name}]', [$this, 'inspiration']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete', 'update-visual'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('panel-admin/pages/read'),
            ],
            ['allow',
                'actions' => ['create', 'inspiration'],
                'expression' => $this->hasAccess('panel-admin/pages/create'),
            ],
            ['allow',
                'actions' => ['update', 'update-visual'],
                'expression' => $this->hasAccess('panel-admin/pages/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('panel-admin/pages/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        return $this->_container->module->render($response, 'pages/view.html', [
            'pages' => $tools->getPages(),
            'session_id' => $this->_user->session_id
        ]);
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        $success = false; $message = []; $page_data = null;
        if (isset($_POST['Page'])){
            if (empty($_POST['Page']['title'])) {
                array_push( $message, 'Judul halaman tidak boleh dikosongi.' );
            }
            if (empty($_POST['Page']['permalink'])) {
                array_push( $message, 'Tuliskan format url yang benar.' );
            }

            $page_data = $_POST['Page'];

            $create = $tools->createPage($_POST['Page']);
            if ($create) {
                if (in_array( 'blockeditor', $this->_extensions) ) {
                    return $response->withRedirect('/panel-admin/pages/inspiration/'.$_POST['Page']['permalink']);
                }

                array_push( $message, 'Halaman Anda telah berhasil disimpan.' );
                $success = true;
            } else {
                if (count($message) == 0)
                    array_push( $message, 'Gagal membuat halaman baru. Halaman '.$_POST['Page']['permalink'].' sudah ada.' );
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'pages/create.html', [
            'message' => ($message) ? $message : null,
            'success' => $success,
            'page_data' => $page_data,
            'has_blockeditor' => (in_array( 'blockeditor', $this->_extensions) ) ? true : false
        ]);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (isset($_POST['content']) && file_exists($_POST['path'])){
            $update = file_put_contents($_POST['path'], $_POST['content']);
            if ($update) {
                $message = 'Data Anda telah berhasil diubah.';
                $success = true;
            } else {
                $message = 'Failed to update the page.';
                $success = false;
            }
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);
        $page_content = $tools->getPage($args['name']);
        $format = new \PanelAdmin\Components\Format();
        $page_content['content'] = $format->HTML($page_content['content']);

        return $this->_container->module->render($response, 'pages/update.html', [
            'data' => $page_content,
            'message' => ($message) ? $message : null,
            'success' => $success
        ]);
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['name'])) {
            return false;
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);
        $delete = $tools->deletePage($args['name']);
        if ($delete) {
            $message = 'Your page is successfully created.';
            echo true;
        }
    }

    public function update_visual($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (isset($_POST['content']) && isset($_POST['path'])){
            $tools = new \PanelAdmin\Components\AdminTools($this->_settings);
            $paths = explode("/", $_POST['path']);
            if (empty($paths[1]))
                $paths[1] = 'index';

            $get_page = $tools->getPage($paths[1], true);
            if (!is_array($get_page))
                return false;

            $html_dom = new \PanelAdmin\Components\DomHelper();

            $class_name = uniqid();
            $current_content = str_replace(array("[[", "]]"), array("{{", "}}"), $get_page['content']);

            $html = $html_dom->str_get_html('<div class="'.$class_name.'">'.$current_content.'</div>');

            foreach ($_POST as $node => $html_data) {
                if (!in_array($node, ['content', 'path'])) {
                    $html->find('.'.$node, 0)->__set('innertext', $html_data);
                }
            }

            $new_content = $html->find('.'.$class_name, 0)->innertext();
            if (empty($new_content))
                return false;

            try {
                $view_path = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views';
                $cp = copy($view_path.'/'.$paths[1] . '.phtml', $view_path.'/backup/'.$paths[1] . '.xhtml');

                $format = new \PanelAdmin\Components\Format();
                $new_content = $format->HTML($new_content);

                $update = file_put_contents($view_path.'/'.$paths[1] . '.phtml', $new_content);
                if ($update) {
                    unlink($view_path.'/staging/'.$paths[1] . '.ehtml');
                }
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }

            return true;
        }
    }

    public function inspiration($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        $content = $tools->getPage($args['name']);
        $inspirations = $tools->getInspirationPages();

        if (isset($_POST['Page']['name'])) {
            $page_path = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/'.$_POST['Page']['name'].'.phtml';
            $inspirations_path = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/inspiration';
            $file_path = $inspirations_path.'/'.$_POST['Page']['theme'];
            if (file_exists($file_path)) {
                $content = file_get_contents( $file_path );
                $html_dom = new \PanelAdmin\Components\DomHelper();
                $html = $html_dom->str_get_html($content);
                $s_content = '';
                foreach ($html->find('section') as $section) {
                    $s_content .= '<section id="'.$section->id.'">'.$section->innertext().'</section>';
                }
                if (!empty($s_content)) {
                    $format = new \PanelAdmin\Components\Format();
                    $s_content = $format->HTML($s_content);
                    $create = $tools->createPage(['content' => $s_content, 'permalink' => $_POST['Page']['name'], 'rewrite' => true]);
                    if ($create) {
                        if (in_array( 'blockeditor', $this->_extensions) ) {
                            return $response->withRedirect('/block-editor/update/'.$_POST['Page']['name']);
                        }
                    }
                }
            }
        }

        return $this->_container->module->render($response, 'pages/inspiration.html', [
            'content' => $content,
            'inspirations' => $inspirations,
            'page_name' => $args['name']
        ]);
    }
}