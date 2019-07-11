<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class ProductsModel extends \Model\BaseModel
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_product';
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
    public function getData( $data = null )
    {
        $sql = 'SELECT t.*, c.title AS category_name  
            FROM {tablePrefix}ext_product t 
            LEFT JOIN {tablePrefix}ext_product_category c ON c.id = t.product_category_id 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.active =:status';
                $params['status'] = $data['status'];
            }
        }

        $sql .= ' ORDER BY t.ordering ASC';
        
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
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name  
            FROM {tablePrefix}ext_product t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    /**
     * @param $id
     * @return float|int
     */
    public function getCurrentCost($id)
    {
        $sql = 'SELECT SUM(t.added_value) AS tot_qty, SUM(t.added_value * t.price) AS tot_price  
            FROM {tablePrefix}ext_purchase_receipt_item t 
            WHERE t.product_id =:product_id AND t.added_in_stock = 1 AND t.removed_value = 0';

        $params = ['product_id' => $id];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row_purchase = R::getRow( $sql, $params );

        $sql2 = 'SELECT SUM(t.added_value) AS tot_qty, SUM(t.added_value * t.price) AS tot_price  
            FROM {tablePrefix}ext_transfer_receipt_item t 
            WHERE t.product_id =:product_id AND t.added_in_stock = 1 AND t.removed_value = 0';

        $params2 = ['product_id' => $id];

        $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

        $row_transfer = R::getRow( $sql2, $params2 );

        $tot_qty = $row_purchase['tot_qty'] + $row_transfer['tot_qty'];
        $tot_price = $row_purchase['tot_price'] + $row_transfer['tot_price'];

        $result = 0;
        if ($tot_qty > 0) {
            $result = $tot_price / $tot_qty;
        }

        return round($result, 2);
    }

    public function getLatestOrder() {
        $sql = 'SELECT MAX(t.ordering) AS max  
            FROM {tablePrefix}ext_product t 
            WHERE 1';

        $params = [];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row['max'];
    }
}
