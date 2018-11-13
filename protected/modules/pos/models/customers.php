<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class CustomersModel extends \Model\BaseModel
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_customer';
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
        ];
    }

    public function getData($data = array())
    {
        $sql = 'SELECT t.*';
        if (isset($data['field'])) {
            $sql = 'SELECT '. $data['field'];
        }

        $sql .= ' FROM {tablePrefix}ext_customer t 
            WHERE 1';

        $params = [];
        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['name'])) {
            $sql .= ' AND LOWER(t.name) LIKE "%'. strtolower($data['name']) .'%"';
        }

        $sql .= ' ORDER BY t.created_at DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}