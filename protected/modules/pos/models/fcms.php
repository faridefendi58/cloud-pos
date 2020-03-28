<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class FcmsModel extends \Model\BaseModel
{
    const STATUS_SENT = 1;
    const STATUS_UNSENT = 0;

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_fcm';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['title', 'required'],
        ];
    }

    public function getData($data = array())
    {
        $sql = 'SELECT t.*';
        if (isset($data['field'])) {
            $sql = 'SELECT '. $data['field'];
        }

        $sql .= ' FROM {tablePrefix}ext_fcm t 
            WHERE 1';

        $params = [];
        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['title'])) {
            $sql .= ' AND LOWER(t.title) LIKE "%'. strtolower($data['title']) .'%"';
        }

        $sql .= ' ORDER BY t.title ASC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getTopics() {
        $items = ['inventory' => 'All Users'];
        $roles = ['administrator', 'cashier', 'cs', 'manager', 'staff', 'virtual_staff'];
        $wh_models =  \Model\WarehousesModel::model()->findAll();
        foreach ($wh_models as $wh_model) {
            foreach ($roles as $i => $role) {
                $_topic = 'fcm_'. $role . '_'. $wh_model->id;
                $items[$_topic] = $wh_model->title . ' - '. ucfirst($role);
            }
        }

        return $items;
    }
}
