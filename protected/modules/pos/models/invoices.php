<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InvoicesModel extends \Model\BaseModel
{
    const STATUS_PAID = 1;
    const STATUS_UNPAID = 0;
    const STATUS_REFUND = 2;

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_invoice';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [];
    }

    public function getInvoiceNumber($status, $type)
    {
        $sql = 'SELECT MAX(t.nr) AS max_nr 
            FROM {tablePrefix}ext_invoice t 
            WHERE t.status =:status';

        $params = [ 'status' => $status ];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        if ($status == self::STATUS_PAID) {
            if (!empty($ext_pos['paid_invoice_series'])) {
                $serie = $ext_pos['paid_invoice_series'];
            } else {
                $serie = 'PAID-'. date("Y").'-';
            }
        } elseif ($status == self::STATUS_UNPAID) {
            if (!empty($ext_pos['unpaid_invoice_series'])) {
                $serie = $ext_pos['unpaid_invoice_series'];
            } else {
                $serie = 'UNPAID-'. date("Y").'-';
            }
        } else {
            if (!empty($ext_pos['refund_invoice_series'])) {
                $serie = $ext_pos['refund_invoice_series'];
            } else {
                $serie = 'REFUND-'. date("Y").'-';
            }
        }

        $next_nr = $row['max_nr'] +1;
        if($type == 'serie')
            return $serie;
        else
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

    public function getData($data = array())
    {
        $sql = 'SELECT t.*, SUM(i.price*i.quantity) AS total, c.name AS customer_name, 
            c.email as customer_email, w.title AS warehouse_name 
            FROM {tablePrefix}ext_invoice t 
            LEFT JOIN {tablePrefix}ext_invoice_item i ON t.id = i.invoice_id 
            LEFT JOIN {tablePrefix}ext_customer c ON c.id = t.customer_id 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            WHERE 1';

        $params = [];
        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        $sql .= ' GROUP BY t.id';
        $sql .= ' ORDER BY t.created_at DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function listStatus()
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_REFUND => 'Retur',
        ];
    }

    public function getStatus($data)
    {
        $statuses = self::listStatus();

        return $statuses[$data['status']];
    }
}