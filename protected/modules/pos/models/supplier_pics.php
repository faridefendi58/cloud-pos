<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class SupplierPicsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_supplier_pic';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['admin_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, s.name AS supplier_name, a.name AS admin_name    
            FROM {tablePrefix}ext_supplier_pic t 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id 
            WHERE 1';

        $params = [];
        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }
        if (isset($data['supplier_id'])) {
            $sql .= ' AND t.supplier_id =:supplier_id';
            $params['supplier_id'] = $data['supplier_id'];
        }

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, s.name AS supplier_name   
            FROM {tablePrefix}ext_supplier_pic t 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
