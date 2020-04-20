<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseStaffsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_staff';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['warehouse_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, a.name AS admin_name, w.title AS warehouse_name, 
            ab.name AS admin_creator_name, r.name AS role_name, 
            g.id AS wh_group_id, g.title AS warehouse_group_name, g.pic AS warehouse_group_pic     
            FROM {tablePrefix}ext_warehouse_staff t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}admin_group r ON r.id = t.role_id 
            LEFT JOIN {tablePrefix}ext_warehouse_group g ON g.id = w.group_id  
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id  
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.created_by   
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
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
            r.title AS role_name  
            FROM {tablePrefix}ext_warehouse_staff t 
            LEFT JOIN {tablePrefix}ext_warehouse_staff_role r ON r.id = t.role_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getAccessWarehouses($data = []) {
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $dt) {
                $items[$dt['id']] = $dt['warehouse_name'];
            }
        }

        return $items;
    }

    public function getCurrentRole($data = []) {
        $sql = 'SELECT t.role_id  
            FROM {tablePrefix}ext_warehouse_staff t 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id' => $data['id']] );

        return (!empty($row))? $row['role_id'] : 1;
    }

    public function getManagers($data = array())
    {
        $sql = 'SELECT t.*, a.group_id      
            FROM {tablePrefix}ext_warehouse_staff t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id   
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        $sql .= ' AND a.group_id = 6';
        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getStockVerifiers($data = array())
    {
        $sql = 'SELECT t.*, a.group_id      
            FROM {tablePrefix}ext_warehouse_staff t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id   
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        $sql .= ' AND a.group_id IN ("2", "3", "5")';
        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}
