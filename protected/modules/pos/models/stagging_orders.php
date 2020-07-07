<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class StaggingOrdersModel extends \Model\BaseModel
{
    const STATUS_ACCEPTED = 1;
    const STATUS_PENDING = 0;

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_stagging_order';
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
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, CONCAT(t.serie, REPEAT(0, 4 - LENGTH(t.nr)), t.nr) AS invoice_number
            FROM {tablePrefix}ext_stagging_order t 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }

			if (isset($data['warehouse_id'])) {
                $sql .= ' AND t.warehouse_id =:warehouse_id';
                $params['warehouse_id'] = $data['warehouse_id'];
            }
        }

        $sql .= ' ORDER BY t.id DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

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
        $sql = 'SELECT t.*
            FROM {tablePrefix}ext_stagging_order t 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

	public function getItem($order_key)
    {
        $sql = 'SELECT t.*, CONCAT(t.serie, REPEAT(0, 4 - LENGTH(t.nr)), t.nr) AS invoice_number
            FROM {tablePrefix}ext_stagging_order t 
            WHERE t.order_key =:order_key';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['order_key' => $order_key] );
		if (!empty($row['items'])) {
			$row['items'] = json_decode($row['items'], true);
		}

        return $row;
    }

	public function getNextNR($warehouse_id, $serie)
    {
        $sql = 'SELECT MAX(t.nr) AS max_nr 
            FROM {tablePrefix}ext_stagging_order t 
            WHERE t.warehouse_id =:warehouse_id AND t.serie =:serie';

        $params = ['warehouse_id' => $warehouse_id, 'serie' => $serie];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        $next_nr = $row['max_nr'] + 1;
        return $next_nr;
    }

    public function getInvoiceFormatedNumber($data = array())
    {
        if(in_array('id', array_keys($data)) && $data['id'] == 0)
            $model = $this;
        else
            $model = self::model()->findByPk($data['id']);

        $nr = str_repeat('0',4-strlen($model->nr));

        return $model->serie.$nr.$model->nr;
    }
}
