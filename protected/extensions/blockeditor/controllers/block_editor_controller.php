<?php

namespace Extensions\Controllers;

use Components\BaseController as BaseController;

class BlockEditorController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/update/elements/thumbs/[{name}]', [$this, 'thumbs']);
        $app->map(['GET', 'POST'], '/update/elements/original/[{name}]', [$this, 'original']);
        $app->map(['GET', 'POST'], '/update/elements/css/[{name}]', [$this, 'css']);
        $app->map(['GET', 'POST'], '/update/elements/[{name}]', [$this, 'elements']);
        $app->map(['GET', 'POST'], '/update/bundles/[{name}]', [$this, 'bundles']);
        $app->map(['GET', 'POST'], '/update/auto-save', [$this, 'auto_save']);
        $app->map(['GET', 'POST'], '/update/[{name}]', [$this, 'update']);
        $app->map(['GET', 'POST'], '/preview/[{name}]', [$this, 'preview']);
        $app->map(['GET', 'POST'], '/publish/[{name}]', [$this, 'publish']);
        $app->map(['GET', 'POST'], '/skeleton', [$this, 'skeleton']);
        $app->map(['GET', 'POST'], '/upload-image', [$this, 'upload_image']);
    }

    public function thumbs($request, $response, $args)
    {
        return $response->withRedirect($this->_settings['params']['site_url'].'/themes/'.$this->_settings['params']['theme'].'/views/staging/thumbs/'.$args['name']);
    }

    public function original($request, $response, $args)
    {
        return $this->_container->view->render($response, 'staging/original/'.$args['name'], [
            'args' => $args
        ]);
    }

    public function css($request, $response, $args)
    {
        //return $response->withRedirect($this->_settings['params']['site_url'].'/themes/'.$this->_settings['params']['theme'].'/assets/build/elements/css/'.$args['name']);
        return $response->withRedirect($this->getVendorUrl().'build/elements/css/'.$args['name']);
    }

    public function elements($request, $response, $args)
    {
        $info = pathinfo($args['name']);
        $file_name =  basename($args['name'],'.'.$info['extension']);

        return $this->_container->view->render($response, 'staging/'.$file_name.'.phtml', [
            'args' => $args
        ]);
    }

    public function bundles($request, $response, $args)
    {
        header('Content-Type: application/octet-stream');

        //$fh = file_get_contents($this->getVendorUrl().'bundles/'.$args['name']);
        $fh = file_get_contents($this->_settings['basePath'].'/extensions/blockeditor/assets/fonts/'.$args['name']);

        return $fh;
    }

    public function update($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (isset($args['name'])){
            $pos = strpos($args['name'], '.');
            if ($pos !== false) {
                header('Content-Type: application/json');
                $file = file_get_contents($this->_settings['basePath'].'/data/'.$args['name']);

                return $file;
            }

            $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

            $get_page = $tools->getPage($args['name'], false);
            if (!is_array($get_page))
                return false;

            $html_dom = new \PanelAdmin\Components\DomHelper();
            $html = $html_dom->str_get_html($get_page['content']);
            $sections = []; $elements = [];
            foreach ($html->find('section') as $section) {
                $s_content = $section->innertext();
                if (!empty($s_content)) {
                    $elements[$args['name'].'-'.$section->id] = array(
                        array(
                            'url' => 'elements/original/'.$args['name'].'-'.$section->id.'.ehtml',
                            'height' => '701',
                            'thumbnail' => 'elements/thumbs/'.$args['name'].'-'.$section->id.'.png'
                        )
                    );
                }
                $class_name = $section->getAttribute('class');
                $sections[$args['name'].'-'.$section->id] = [
                    'content' => $section->innertext(),
                    'class' => $class_name,
                    'id' => $section->id
                ];
            }

            try {
                $this->create_elements($elements);
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }

            try {
                $this->create_section($sections);
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }

            return $this->_container->view->render($response, 'staging/index.phtml', [
                'sections' => $sections,
                'page' => $args['name']
            ]);
        }
    }

    /**
     * @param $data
     * @return bool
     */
    private function create_elements($data)
    {
        $elements_data = ['elements' => $data];
        $file_path = $this->_settings['basePath'] . '/data/';
        if (!file_exists($file_path.'elements.json')) {
            $fp = fopen($file_path.'elements.json', "wb");
            fwrite($fp, json_encode($elements_data));
            fclose($fp);
        } else {
            file_put_contents($file_path.'elements.json', json_encode($elements_data));
        }

        return true;
    }

    private function create_section($data)
    {
        $html_dom = new \PanelAdmin\Components\DomHelper();
        $format = new \PanelAdmin\Components\Format();
        
        $file_path = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/staging/original';
        $basic = file_get_contents($file_path.'/basic.html');

        foreach ($data as $section_name => $section_data) {
            if (!file_exists($file_path.'/'.$section_name.'.ehtml')) {
                $fp = fopen($file_path.'/'.$section_name.'.ehtml', "wb");
                fwrite($fp, $section_data['content']);
                fclose($fp);
            }

            $html = $html_dom->str_get_html($basic);
            if (!empty($section_data['class']))
                $innertext = '<section id="'.$section_data['id'].'" class="'.$section_data['class'].'">'.$section_data['content'].'</section>';
            else
                $innertext = '<section id="'.$section_data['id'].'">'.$section_data['content'].'</section>';
            $html->find('.page', 0)->__set('innertext', $innertext);

            $new_content = $html->find('html', 0)->innertext();
            $new_content = $format->HTML('<html lang="en">'.$new_content.'</html>');

            $update = file_put_contents($file_path.'/'.$section_name.'.ehtml', '<!DOCTYPE html>'.$new_content);
        }
        
        return true;
    }

    public function preview($request, $response, $args)
    {
        $html_dom = new \PanelAdmin\Components\DomHelper();
        $html = $html_dom->str_get_html($_POST['page']);
        $new_content = $html->find('.page', 0)->innertext();

        $content = '{% extends "partial/layout.phtml" %}{% block pagetitle %}{{ App.params.tag_line }} - {{App.name}}{% endblock %}{% block content %}';
        $content .= $new_content;
        $content .= '{% endblock %}';

        $format = new \PanelAdmin\Components\Format();
        $pageContent = $format->HTML( $content );

        $filename = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/staging/original/'.$args['name'].'.preview.ehtml';

        $previewFile = fopen($filename, "w");

        fwrite($previewFile, $pageContent);

        fclose($previewFile);

        return $this->_container->view->render($response, 'staging/original/'.$args['name'].'.preview.ehtml', [
            'args' => $args
        ]);
    }

    public function skeleton($request, $response, $args)
    {
        return $this->_container->view->render($response, 'staging/skeleton.phtml', [
            'args' => $args
        ]);
    }

    public function publish($request, $response, $args)
    {
        if (empty($_POST['page'])) {
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Gagal menerbitkan situs Anda!');
        }

        $html_dom = new \PanelAdmin\Components\DomHelper();
        $html = $html_dom->str_get_html($_POST['page']);
        $new_content = $html->find('.page', 0)->innertext();

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        $get_page = $tools->getPage($args['name']);

        $page_filename = $get_page['path'];

        $content = '{% extends "partial/layout.phtml" %}{% block pagetitle %}{{ App.params.tag_line }} - {{App.name}}{% endblock %}{% block content %}';
        $content .= $new_content;
        $content .= '{% endblock %}';

        $format = new \PanelAdmin\Components\Format();
        $pageContent = $format->HTML( $content );

        $update = file_put_contents( $page_filename, stripcslashes($pageContent) );
        if ($update) {
            $preview_file = $this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/staging/original/'.$args['name'].'.preview.ehtml';
            if (file_exists( $preview_file ))
                unlink( $preview_file );
            // remove the stagings files
            foreach (glob($this->_settings['theme']['path'] . '/' . $this->_settings['theme']['name'] . '/views/staging/original/'.$args['name'].'-*') as $filename) {
                if (file_exists($filename))
                    unlink($filename);
            }
        }

        return $response->withRedirect( '/'.$args['name'] );
    }

    private function getVendorUrl()
    {
        $vendor_url = $this->getBaseUrl().'/';

        if (!empty($this->_settings['params']['ext_blockeditor'])) {
            $configs = json_decode($this->_settings['params']['ext_blockeditor'], true);
            $vendor_url = $configs['vendor_url'];
        }

        return $vendor_url;
    }

    public function auto_save($request, $response, $args)
    {
        return true;
    }

    public function upload_image($request, $response, $args)
    {
        $uploads_dir = $this->_settings['basePath'].'/../uploads/pages';//specify the upload folder, make sure it's writable!
        if (!is_dir($uploads_dir)) {
            mkdir( $uploads_dir, 0777, true );
        }

        $relative_path = $this->getBaseUrl($request).'/uploads/pages';//specify the relative path from your elements to the upload folder

        $allowed_types = [
            "image/jpeg", "image/gif", "image/png", "image/svg", "application/pdf"
        ];

        /* DON'T CHANGE ANYTHING HERE!! */

        $return = array();


        //does the folder exist?
        if( !file_exists( $uploads_dir ) ) {

            $return['code'] = 0;
            $return['response'] = "The specified upload location does not exist. Please provide a correct folder in /_upload.php";

            die( json_encode( $return ) );

        }

        //is the folder writable?
        if( !is_writable( $uploads_dir ) ) {

            $return['code'] = 0;
            $return['response'] = "The specified upload location is not writable. Please make sure the specified folder has the correct write permissions set for it.";

            die( json_encode( $return ) );

        }

        if ( !isset($_FILES['imageFileField']['error']) || is_array($_FILES['imageFileField']['error']) ) {

            $return['code'] = 0;
            $return['response'] = $_FILES['imageFileField']['error'];

            die( json_encode( $return ) );

        }

        $name = $_FILES['imageFileField']['name'];

        $file_type = $_FILES['imageFileField']['type'];


        if ( in_array($file_type, $allowed_types) ) {

            if ( move_uploaded_file( $_FILES['imageFileField']['tmp_name'], $uploads_dir."/".$name ) ) {

            } else {
                $return['code'] = 0;
                $return['response'] = "The uploaded file couldn't be saved. Please make sure you have provided a correct upload folder and that the upload folder is writable.";
            }

            $return['code'] = 1;
            $return['response'] = $relative_path."/".$name;

        } else {

            $return['code'] = 0;
            $return['response'] = "File type not allowed";

        }

        return json_encode( $return );
    }
}