<?php
/**
 * Goo Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Goo;
use WP_Query;

/**
 * Goo用RSS
 */
class ExtendGoo extends Goo {
    protected $id = 'goo';
    protected $is_use = false;

    protected function get_query_arg() {}
    protected function render_item( $item ) {}
}
