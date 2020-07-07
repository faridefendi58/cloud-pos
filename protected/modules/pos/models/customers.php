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
        $sql = 'SELECT t.*, 
          (SELECT MAX(i.id) FROM {tablePrefix}ext_invoice i WHERE i.customer_id=t.id AND i.status =1) AS last_invoice_id, 
          (SELECT CASE WHEN o.discount IS NULL THEN SUM(o.price*o.quantity) ELSE SUM((o.price-o.discount)*o.quantity) END AS tot_order FROM {tablePrefix}ext_order o LEFT JOIN {tablePrefix}ext_invoice iv ON iv.id=o.invoice_id WHERE o.customer_id=t.id AND iv.status=1) AS tot_spend';
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

		if (isset($data['email'])) {
            $sql .= ' AND t.email =:email';
            $params['email'] = $data['email'];
        }

        if (isset($data['name'])) {
            $sql .= ' AND LOWER(t.name) LIKE "%'. strtolower($data['name']) .'%"';
        }

		if (isset($data['telephone'])) {
            $sql .= ' AND t.telephone LIKE "%'. $data['telephone'] .'%"';
        }

		if (isset($data['group_id'])) {
            $sql .= ' AND t.group_id =:group_id';
            $params['group_id'] = $data['group_id'];
        }

		if (isset($data['order_by'])) {
			$special_orders = ['highest_order' => 'tot_spend DESC', 'latest_order' => 'last_invoice_id DESC'];
			if (!array_key_exists($data['order_by'], $special_orders)) {
            	$sql .= ' ORDER BY t.'. $data['order_by'] .' ASC';
			} else {
				//special ordering
                $sql .= ' ORDER BY '. $special_orders[$data['order_by']];
			}
        } else {
	        $sql .= ' ORDER BY t.name ASC';
		}

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getDetail($id)
    {
        $sql = 'SELECT t.*, g.name AS group_name
            FROM {tablePrefix}ext_customer t 
            LEFT JOIN {tablePrefix}ext_customer_group g ON g.id = t.group_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
