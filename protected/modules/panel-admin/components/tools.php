<?php
namespace PanelAdmin\Components;

class AdminTools
{
    protected $basePath;
    protected $themeName;

    public function __construct($settings)
    {
        $this->basePath = (is_object($settings))? $settings['basePath'] : $settings['settings']['basePath'];
        $this->themeName = (is_object($settings))? $settings['theme']['name'] : $settings['settings']['theme']['name'];
    }

	/**
	 * Get all pages under themes folder
	 * @return array
	 */
    public function getPages()
    {
        $pages = array();
        foreach (glob($this->basePath.'/../themes/'.$this->themeName.'/views/*.phtml') as $filename) {
            $page = basename($filename, '.phtml');
            if ( $page == 'index' ){
                $name = 'Home';
            } else {
                $name = ucwords( implode(" ", explode("-", $page)) );
            }
			$excludes = ['post'];
			if (!in_array($page, $excludes))
            	$pages[] = [ 'name' => $name, 'slug' => $page, 'path' => $filename, 'info' => pathinfo($filename) ];
        }

        return $pages;
    }

	/**
	 * Geting the detail page information
	 * @param $slug
	 * @return array|bool
	 */
	public function getPage($slug, $staging_file = false)
	{
		$path = $this->basePath.'/../themes/'.$this->themeName.'/views/'.$slug.'.phtml';
		if ($staging_file)
			$path = $this->basePath.'/../themes/'.$this->themeName.'/views/staging/'.$slug.'.ehtml';
		if (!file_exists($path))
			return false;
		return [ 'page' => $slug, 'path' => $path, 'content' => file_get_contents($path) ];
	}

	/**
	 * Create new page, new phtml file inside themes directory
	 * @param $data
	 * @return bool
	 */
	public function createPage($data)
	{
		if (is_array(self::getPage($data['permalink']))) {
			if (!isset($data['rewrite']))
				return false;
		}

		// create the file if not rewrite page
		if (!isset($data['rewrite'])) {
			$slug = str_replace(" ", "-", strtolower($data['permalink']));
			$fp = fopen($this->basePath.'/../themes/'.$this->themeName.'/views/'.$slug.'.phtml', "wb");
			$content = '{% extends "partial/layout.phtml" %}';
			if (isset($data['title']))
				$content .= '{% block pagetitle %}'.$data['title'].' - {{App.name}}{% endblock %}';
			else
				$content .= '{% block pagetitle %}{{ App.params.tag_line }} - {{App.name}}{% endblock %}';

			if (isset($data['meta_keyword']))
				$content .= '{% block meta_keyword %}'.$data['meta_keyword'].'{% endblock %}';

			if (isset($data['meta_description']))
				$content .= '{% block meta_description %}'.$data['meta_description'].'{% endblock %}';

			$content .= '{% block content %}';
			if (empty($data['content'])) {
				$content .= '<section id="'.$slug.'"></section>';
			} else {
				$content .= $data['content'];
			}
			$content .= '{% endblock %}';
			fwrite($fp, $content);
			fclose($fp);
		} else {
			include_once __DIR__ . '/simple_html_dom.php';

			$file_path = $this->basePath.'/../themes/'.$this->themeName.'/views/'.$data['permalink'].'.phtml';
			$content = file_get_contents( $file_path );
			$html_dom = new \PanelAdmin\Components\DomHelper();
			$html = $html_dom->str_get_html( $content );
			$html->find('section', 0)->outertext = $data['content'];
			$html->save($file_path);
		}

		return true;
	}

	/**
	 * Delete Page
	 * @param $slug
	 * @return bool
	 */
	public function deletePage($slug)
	{
		$pages = self::getPage($slug);
		if (!is_array($pages))
			return false;

		// delete the file
		unlink($pages['path']);

		return true;
	}

	/**
	 * List of themes
	 * @return array
	 */
	public function getThemes()
	{
		$items = array();
		foreach (scandir($this->basePath.'/../themes') as $dir) {
			if ( !in_array($dir, ['.', '..']) && is_dir($this->basePath.'/../themes/'.$dir) ){
				if (file_exists($this->basePath.'/../themes/'.$dir.'/manifest.json')){
					$manifest = file_get_contents($this->basePath.'/../themes/'.$dir.'/manifest.json');
					$item = json_decode($manifest, true);

					if (!is_array($item)){
						$item = ['id'=>$dir, 'name'=>ucfirst($dir), 'preview'=>'screenshot.png'];
					}

					$item ['path'] = $this->basePath.'/../themes/'.$dir;
					$item ['img_path'] = 'themes/'.$dir.'/'.$item['preview'];
					$items[$dir] = $item;
				}
			}
		}

		return $items;
	}

	public function getThemeConfig()
	{
		return \Components\Application::getThemeConfig();
	}

	/**
	 * @return array
	 */
	public function getExtensions()
	{
		$items = array();
		foreach (scandir($this->basePath.'/extensions') as $dir) {
			if ( !in_array($dir, ['.', '..']) && is_dir($this->basePath.'/extensions/'.$dir) ){
				if (file_exists($this->basePath.'/extensions/'.$dir.'/manifest.json')){
					$manifest = file_get_contents($this->basePath.'/extensions/'.$dir.'/manifest.json');
					$item = json_decode($manifest, true);

					if (!is_array($item)){
						$item = ['id'=>$dir, 'name'=>ucfirst($dir), 'icon'=>'icon.png'];
					}

					$item ['path'] = $this->basePath.'/extensions/'.$dir;
					$item ['icon'] = 'extensions/'.$dir.'/'.$item['icon'];
					$items[$dir] = $item;
				}
			}
		}

		return $items;
	}

	/**
	 * @param $id
	 * @return array|bool|mixed
	 */
	public function getExtension($id)
	{
		if (file_exists($this->basePath.'/extensions/'.$id.'/manifest.json')){
			$manifest = file_get_contents($this->basePath.'/extensions/'.$id.'/manifest.json');
			$item = json_decode($manifest, true);

			return (!is_array($item))? false : $item;
		}

		return false;
	}

	/**
	 * Get all pages under themes folder
	 * @return array
	 */
	public function getInspirationPages()
	{
		$pages = array();
		foreach (glob($this->basePath.'/../themes/'.$this->themeName.'/views/inspiration/*.phtml') as $filename) {
			$page = basename($filename, '.phtml');
			$name = ucwords( implode(" ", explode("-", $page)) );
			if (!in_array($page, $excludes))
				$pages[] = [
					'name' => $name,
					'slug' => $page,
					'path' => $filename,
					'info' => pathinfo($filename) ,
					'image_thumb' => 'uploads/inspirations/'.$this->themeName.'/'.$page.'.png'
				];
		}

		return $pages;
	}
}
