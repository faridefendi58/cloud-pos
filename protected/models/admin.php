<?php
namespace Model;

require_once __DIR__ . '/base.php';

class AdminModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'admin';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['username, email, group_id, status', 'required'],
            ['username', 'length', 'min'=>3, 'max'=>32],
            ['username, email', 'unique'],
            ['password', 'required', 'on'=>'create'],
            ['password', 'length', 'min'=>8, 'on'=>'create'],
            ['email', 'email'],
            ['group_id', 'numerical', 'integerOnly' => true],
        ];
    }

    public function hasPassword($password, $salt)
    {
        return md5($salt.$password);
    }

    public function getListGroup()
    {
        return [1=>'Administrator', 2=>'Staff'];
    }

    public function getGroup($group_id)
    {
        $groups = self::getListGroup();
        return $groups[$group_id];
    }

    public function getListStatus()
    {
        return ['Tidak Aktif', 'Aktif'];
    }

    public function getStatus($status)
    {
        $items = self::getListStatus();
        return $items[$status];
    }

    public function getPriviledge($data)
    {
        $sql = 'SELECT g.priviledge 
          FROM tbl_admin a 
          LEFT JOIN tbl_admin_group g ON g.id = a.group_id 
          WHERE a.id =:id';

        $priv = R::getRow( $sql, [ 'id'=>$data['user_id'] ]);
        return json_decode($priv['priviledge'], true);
    }
}
