<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseGroupsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_group';
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
    public function getData()
    {
        $sql = 'SELECT t.*, a.name AS admin_name   
            FROM {tablePrefix}ext_warehouse_group t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name  
            FROM {tablePrefix}ext_warehouse_group t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getDataByPic($data)
    {
        $sql = 'SELECT t.id, t.title    
            FROM {tablePrefix}ext_warehouse_group t 
            WHERE 1';

        if (isset($data['admin_id']) && isset($data['admin_name'])) {
            $key = '"'. $data['admin_id'] .'":{"name":"'. $data['admin_name'] .'"';
            $sql .= " AND t.pic LIKE '%".$key."%'";
        }

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }
}
