<?php
namespace Model;

require_once __DIR__ . '/base.php';

class OptionsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'options';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['option_name, option_value', 'required'],
        ];
    }

    public function getOptions()
    {
        $sql = 'SELECT t.option_name, t.option_value  
          FROM {tablePrefix}options t 
          WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $options = R::getAll( $sql );
        $items = [];
        if (is_array($options)) {
            foreach ($options as $i => $option) {
                $items[$option['option_name']] = $option['option_value'];
            }
        }

        return $items;
    }

    /**
     * Installing the new extension from ext service
     * @param $sql
     * @param $params
     * @return int
     */
    public function installExt($sql, $params = array())
    {
        $execute = R::exec($sql, $params);
        return $execute;
    }

    public function getInstalledExtensions()
    {
        $sql = 'SELECT t.option_value  
          FROM {tablePrefix}options t 
          WHERE t.option_name = :option_name';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $col = R::getRow( $sql, ['option_name' => 'extensions'] );

        if (!empty($col)) {
            $exts = json_decode($col['option_value'], true);
            if (!is_array($exts))
                return false;

            $items = [];
            foreach ($exts as $ext) {
                if (file_exists(__DIR__ .'/../extensions/'.$ext.'/manifest.json')){
                    $manifest = file_get_contents(__DIR__ .'/../extensions/'.$ext.'/manifest.json');
                    $item = json_decode($manifest, true);

                    if (!is_array($item)){
                        $item = ['id'=>$ext, 'name'=>ucfirst($ext), 'icon'=>'icon.png'];
                    }

                    $item ['icon'] = 'protected/extensions/'.$ext.'/'.$item['icon'];
                    $service_class_name = ucfirst($ext).'Service';
                    $service_class_name = "Extensions\\".ucfirst($ext)."Service";
                    $service = new $service_class_name();

                    if (is_object($service) && method_exists($service, 'getMenu')) {
                        $item ['menu'] = $service->getMenu();
                    }
                    $items[$ext] = $item;
                }

            }

            return $items;
        }

        return false;
    }
}
