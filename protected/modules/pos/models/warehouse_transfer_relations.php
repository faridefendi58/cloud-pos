<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseTransferRelationsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_transfer_relation';
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
        $sql = 'SELECT t.*, a.name AS admin_name, w.title AS warehouse_name     
            FROM {tablePrefix}ext_warehouse_transfer_relation t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_rel_id           
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by   
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['rel_type'])) {
            $sql .= ' AND t.rel_type =:rel_type';
            $params['rel_type'] = $data['rel_type'];
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
    public function getRelatedWarehouses($data = [])
    {
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $data) {
                $items[$data['id']] = $data['warehouse_name'];
            }
        }

        return $items;
    }
}
