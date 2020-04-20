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

            if (empty($user->id) && !empty($_COOKIE[$user->session_id])) {
                $user_cookie = json_decode($_COOKIE[$user->session_id], true);
                if (is_array($user_cookie)) {
                    $user->id = $user_cookie['user']['id'];
                }
            }
            
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

	public function getData($data = array())
    {
        $sql = 'SELECT t.*  
            FROM {tablePrefix}admin_group t 
            WHERE 1';

        $params = [];

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getItem($data = array())
    {
        $sql = 'SELECT t.*  
            FROM {tablePrefix}admin_group t 
            WHERE 1';

        $params = [];
		if (isset($data['id'])) {
			$sql .= ' AND t.id =:id';
            $params['id'] = $data['id'];
		}

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
