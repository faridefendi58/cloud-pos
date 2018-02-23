<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class ProductStocksModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_product_stock';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['product_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($product_id = 0)
    {
        $sql = 'SELECT t.*, p.unit, w.title AS warehouse_name     
            FROM {tablePrefix}ext_product_stock t 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            WHERE 1';

        $params = [];
        if ($product_id > 0) {
            $sql .= ' AND t.product_id =:product_id';
            $params['product_id'] = $product_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $product_id
     * @param int $warehouse_id
     * @return mixed
     */
    public function getTotalStock($product_id, $warehouse_id = 0)
    {
        $sql = 'SELECT SUM(t.quantity) AS total     
            FROM {tablePrefix}ext_product_stock t 
            WHERE t.product_id =:product_id';

        $params = ['product_id' => $product_id];
        if ($warehouse_id > 0) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $warehouse_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row['total'];
    }
}
