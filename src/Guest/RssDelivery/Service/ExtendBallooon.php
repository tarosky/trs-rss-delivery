<?php
/**
 * Ballooon Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Ballooon;
use WP_Query;

/**
 * Ballooon用RSS
 */
class ExtendBallooon extends Ballooon {
    protected $id = 'ballooon';
    protected $is_use = false;

    protected function get_query_arg() {}
    protected function render_item( $item ) {}
}
