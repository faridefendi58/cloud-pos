<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehousesModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse';
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

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, g.title AS group_name, g.pic AS group_pic    
            FROM {tablePrefix}ext_warehouse t 
            LEFT JOIN {tablePrefix}ext_warehouse_group g ON g.id = t.group_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.active =:status';
                $params['status'] = $data['status'];
            }
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
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name, 
            g.title AS group_name, g.pic AS group_pic   
            FROM {tablePrefix}ext_warehouse t 
            LEFT JOIN {tablePrefix}ext_warehouse_group g ON g.id = t.group_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getItem($data = [])
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name, 
            g.title AS group_name, g.pic AS group_pic   
            FROM {tablePrefix}ext_warehouse t 
            LEFT JOIN {tablePrefix}ext_warehouse_group g ON g.id = t.group_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$data['id']] );

        return $row;
    }

    public function getListWH($data = null)
    {
        $sql = 'SELECT t.id, t.title    
            FROM {tablePrefix}ext_warehouse t 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.active =:status';
                $params['status'] = $data['status'];
            }
        }

        $sql .= ' ORDER BY t.id ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        foreach ($rows as $i => $row) {
            $items[$row['id']] = $row['title'];
        }

        return $items;
    }

    public function getProducts($data = array())
    {
        $sql = 'SELECT t.product_id, p.code AS product_code, p.title AS product_name, p.unit AS product_unit       
            FROM {tablePrefix}ext_warehouse_product t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['product_id'])) {
            $sql .= ' AND t.product_id =:product_id';
            $params['product_id'] = $data['product_id'];
        }

        if (isset($data['warehouse_id'])) {
            $sql .= ' ORDER BY t.priority ASC';
        } else {
            $sql .= ' ORDER BY t.id ASC';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}
