<?php
namespace PanelAdmin\Components;

class AdminHooks
{
    protected $basePath;
    protected $themeName;
    protected $adminPath;

    public function __construct($settings)
    {
        $this->basePath = (is_object($settings))? $settings['basePath'] : $settings['settings']['basePath'];
        $this->themeName = (is_object($settings))? $settings['theme']['name'] : $settings['settings']['theme']['name'];
        $this->adminPath = (is_object($settings))? $settings['admin']['path'] : $settings['settings']['admin']['path'];
    }
	
	public function onAfterParamsSaved($data)
	{
        try {
            $params_path = $this->basePath.'/data/configs.json';
            if (!file_exists($params_path)){
                $file = fopen($params_path, 'w');
            }
            $update = file_put_contents($params_path, json_encode($data));

        } catch (\Exception $e) {
            return;
        }

		return true;
	}
}
