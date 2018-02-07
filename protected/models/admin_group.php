<?php
namespace Model;

require_once __DIR__ . '/base.php';

class AdminGroupModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'admin_group';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['name', 'required'],
            ['name', 'length', 'max'=>64],
        ];
    }

    public function hasAccess($user, $route)
    {
        if (strpos($route, '/')){
            $routes = explode("/", $route);
            $model = new \Model\AdminModel();
            $params = [
                'user_id' => $user->id
            ];
            $priv = $model->getPriviledge($params);

            $module = $routes[count($routes)-3];
            $controller = $routes[count($routes)-2];
            $action = end($routes);

            if (is_array($priv) && !empty($priv[$module][$controller][$action]))
                return true;
        }

        return false;
    }
}
