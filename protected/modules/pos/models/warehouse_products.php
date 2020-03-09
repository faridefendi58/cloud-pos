<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseProductsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_product';
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
            p.title AS product_name, p.unit AS product_unit, p.config AS product_config       
            FROM {tablePrefix}ext_warehouse_product t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by   
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

    public function getPricesByWH($data) {
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $i => $data) {
                $data['configs'] = json_decode($data['configs'], true);
                $items[$data['id']] = $data;
            }
        }

        return $items;
    }

    public function getCurrentCost($data = array())
    {
        $sql = 'SELECT t.current_cost       
            FROM {tablePrefix}ext_warehouse_product t 
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

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row['current_cost'];
    }
}
