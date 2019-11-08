<?php
/**
 * RSS基本動作抽象クラス
 *
 * @package Guest
 */

namespace Tarosky\FeedGenerator;

use DateTime;
use DateTimeZone;
use Tarosky\FeedGenerator\Model\Singleton;
use WP_Query;

/**
 * RSS用基盤抽象クラス
 */
abstract class AbstractFeed extends Singleton {

    /**
     * 表示するかどうか.
     *
     * @var boolean $is_use このRSSを表示させるか否か
     */
    protected $is_use = true;

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = '';

	/**
	 * サービスごとの表示名.
	 *
	 * @var string $label 表示ラベル.
	 */
	protected $label = '';

    /**
     * 対象ポストタイプリスト.
     *
     * @var array $target_post_types ポストタイプ.
     */
    protected $target_post_types = [ 'post' ];

	/**
	 * 表示件数.
	 *
	 * @var int $per_page 表示件数.
	 */
	protected $per_page = 20;

	/**
	 * 表示順の優先度.
	 *
	 * @var int $order_priolity 表示優先度 大きいほうが優先度が高い.
	 */
	protected $order_priolity = 1;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array WP_Query に渡す配列
	 */
	abstract protected function get_query_arg();

	/**
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	abstract protected function render_item( $post );

	/**
	 * GMTの日付を特定のタイムゾーンに合わす
	 *
	 * @param string  $date 日付.
	 * @param string  $format フォーマット.
	 * @param string  $local_timezone 指定タイムゾーン デフォルトは設定タイムゾーン.
	 * @param boolean $is_gmt 引き渡す日付がgmtか.
	 *
	 * @return string
	 */
	protected function to_local_time( $date, $format, $local_timezone = '', $is_gmt = false ) {
		if ( ! $local_timezone ) {
			$local_timezone = get_option( 'timezone_string' );
		}
		if ( $local_timezone ) {
			if ( $is_gmt ) {
				$date = new DateTime( $date );
				$date->setTimeZone( new DateTimeZone( $local_timezone ) );
				return $date->format( $format );
			} else {
				$date = new DateTime( $date, new DateTimeZone( $local_timezone ) );
				return $date->format( $format );
			}
		} else {
			return $date;
		}
	}

    protected function get_feed_build_date( $format ) {
	    $feed_build_date = '';
        if ( function_exists( 'get_feed_build_date' ) ) {
            $feed_build_date = $this->to_local_time( get_feed_build_date( $format ), $format, get_option('timezone_string'), true );
        } else {
            $date = get_lastpostmodified( 'GMT' );
            $date = $date ? mysql2date( $format, $date, false ) : date( $format );
            $feed_build_date = $this->to_local_time( $date, $format, get_option('timezone_string'), true );
        }

	    return $feed_build_date;
    }

	/**
	 * XMLヘッダーを吐く
	 *
	 * @param int $hours 期限, デフォルトは1時間.
	 */
	protected function xml_header( $hours = 1 ) {
		if ( $hours ) {
			$this->expires_header( $hours );
		}
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?>' . "\n";

	}

	/**
	 * Expiresヘッダーを吐く
	 *
	 * @param int $hours 期限, デフォルトは1時間.
	 */
	protected function expires_header( $hours = 1 ) {
		$time = current_time( 'timestamp', true ) + 60 * 60 * $hours;
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', $time ) . ' GMT' );
	}

    /**
     * 表示するかどうか。
     *
     * @return int
     */
    public function is_use() {
        return $this->is_use;
    }

    /**
     * IDを取得する。
     *
     * @return string
     */
    public function get_target_post_types() {
        return $this->target_post_types;
    }

	/**
	 * IDを取得する。
	 *
	 * @return string
	 */
	public function get_id() {
		$class_name = explode( '\\', get_called_class() );
		return $this->id ? $this->id : strtolower( end( $class_name ) );
	}

	/**
	 * サービスの表示名を取得する。各サービスでlabelに指定がされている場合はそれを使用。無ければクラス名が使われる。
	 *
	 * @return string
	 */
	public function get_label() {
		$class_name = explode( '\\', get_called_class() );
		return $this->label ? $this->label : end( $class_name );
	}

	/**
	 * 表示優先度の値を返す。
	 *
	 * @return int
	 */
	public function get_priolity() {
		return $this->order_priolity;
	}

	/**
	 * クエリの上書き
	 *
	 * @param WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {
		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
	}

	/**
	 * 設定したカスタムロゴURLを返す
	 *
	 * @param bool $is_rectangle Whether it is rectangular.
	 * @return string $logo_url ロゴURL.
	 */
	public function get_logo_url( $is_rectangle = false ) {
		$logo_url = '';
		if ( has_custom_logo() ) {
			$logo     = get_theme_mod( 'custom_logo' );
			$logo_url = wp_get_attachment_url( $logo );
		}
		return $logo_url;
	}
}
