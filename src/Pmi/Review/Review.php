<?php
namespace Pmi\Review;

use Pmi\Order\Order;

class Review
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public static $orderStatus = [
        'created_ts' => 'Created',
        'collected_ts' => 'Collected',
        'processed_ts' => 'Processed',
        'finalized_ts' => 'Finalized'
    ];

    protected function getTodayOrderRows($today)
    {
        $ordersQuery = 'SELECT o.*, ' .
            'oh.type as h_type, ' .
            's.name as created_site_name, ' .
            'sc.name as collected_site_name, ' .
            'sp.name as processed_site_name, ' .
            'sf.name as finalized_site_name ' .
            'FROM orders o ' .
            'LEFT JOIN orders_history oh ON o.history_id = oh.id ' .
            'LEFT JOIN sites s ON s.site_id = o.site ' .
            'LEFT JOIN sites sc ON sc.site_id = o.collected_site ' .
            'LEFT JOIN sites sp ON sp.site_id = o.processed_site ' .
            'LEFT JOIN sites sf ON sf.site_id = o.finalized_site ' .
            'WHERE ' .
            '(o.created_ts >= :today OR o.collected_ts >= :today OR o.processed_ts >= :today OR o.finalized_ts >= :today OR oh.created_ts >= :today) ' .
            'ORDER BY o.created_ts DESC';

        return $this->db->fetchAll($ordersQuery, [
            'today' => $today
        ]);
    }

    public function getTodayOrders($today)
    {
        $orders = [];
        foreach ($this->getTodayOrderRows($today) as $row) {
            // Get order status
            foreach (self::$orderStatus as $field => $status) {
                if ($row[$field]) {
                    $row['orderStatus'] = self::getOrderStatus($row, $status);
                }
            }
            // Get number of finalized samples
            if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                $row['finalizedSamples'] = count($samples);
            }
            $orders[] = $row;
        }

        return $orders;
    }

    public static function getOrderStatus($row, $status)
    {
        $type = $row['h_type'];
        if ($type === Order::ORDER_CANCEL) {
            $status = 'Cancelled';
        } elseif ($type === Order::ORDER_UNLOCK) {
            $status = 'Unlocked';
        } elseif ($type === Order::ORDER_EDIT) {
            $status = 'Edited & Finalized';
        } elseif (!empty($row['finalized_ts']) && empty($row['rdr_id'])) {
            $status = 'Processed';
        } elseif (!empty($row['finalized_ts']) && $row['biobank_finalized']) {
            $status = 'Biobank Finalized';
        }
        return $status;
    }
}
