<?php
/**
Plugin Name: TrsFeed
Plugin URI:
Description:
Author: TAROSKY INC.
Author URI: https://tarosky.co.jp
Version: 1.0.0
*/
use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\RouterManager;

require_once __DIR__ . '/vendor/autoload.php';

DeliveryManager::instance();
