<?php
/**
 * GoogleNews Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\GoogleNews;
use WP_Query;

/**
 * GoogleNewsSite用RSS
 */
class ExtendGoogleNews extends GoogleNews {
    protected $id = 'googlenews';
    protected $is_use = false;

    protected function get_query_arg() {}
    protected function render_item( $item ) {}
}
