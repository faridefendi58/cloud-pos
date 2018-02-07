<?php
namespace ExtensionsModel;

require_once __DIR__ . '/../../../models/base.php';

class ClientOrderModel extends \Model\BaseModel
{
    const STATUS_PENDING_SETUP = "pending_setup";
    const STATUS_FAILED_SETUP = "failed_setup";
    const STATUS_ACTIVE = "active";
    const STATUS_CANCELED = "canceled";
    const STATUS_SUSPENDED = "suspended";
    const INVOICE_OPTION_NO_INVOICE = "no-invoice";
    const INVOICE_OPTION_ISSUE_INVOICE = "issue-invoice";

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_client_order';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['client_id', 'required'],
        ];
    }

    public function get_list()
    {
        $sql = "SELECT t.*, p.title AS product_title      
            FROM {tableName} t 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1 
            ORDER BY t.id DESC";

        $sql = str_replace(['{tableName}', '{tablePrefix}'], [$this->tableName, $this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql );

        return $rows;
    }

    public function get_status($status = null)
    {
        $status_list = [
            self::STATUS_PENDING_SETUP => "Pending Setup",
            self::STATUS_FAILED_SETUP => "Failed Setup",
            self::STATUS_ACTIVE => "Active",
            self::STATUS_CANCELED => "Canceled",
            self::STATUS_SUSPENDED => "Suspended"
        ];

        if (empty($status))
            return $status_list;
        else
            return $status_list[$status];
    }

    public function get_service($id)
    {
        $order = $this->model()->findByPk($id);
        if (empty($order->service_type) || empty($order->service_id))
            return false;

        $sql = "SELECT t.*       
            FROM {tablePrefix}ext_service_{service_type} t 
            WHERE t.id = :id";

        $sql = str_replace(['{tablePrefix}', '{service_type}'], [$this->_tbl_prefix, $order->service_type], $sql);

        $row = \Model\R::getRow( $sql, ['id'=>$order->service_id] );

        return $row;
    }

    public function find_order_by_hash($hash)
    {
        $sql = "SELECT t.id        
            FROM {tableName} t 
            WHERE t.config LIKE '%{hash}%'";

        $sql = str_replace(['{tableName}', '{hash}'], [$this->tableName, '"h":"'.$hash.'"'], $sql);

        $row = \Model\R::getRow( $sql, ['id'=>$order->service_id] );

        if (is_array($row) && !empty($row['id'])) {
            $model = $this->model()->findByPk($row['id']);

            return $model;
        }

        return false;
    }
}
