<?php
/**
 * FujiTVMain Feed
 *
 * @package Guest
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * FujiMainSite用RSS
 */
class FujiMain extends AbstractFeed {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = 'fujitv';
    protected $is_use = false;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'fujimain',
			'posts_per_rss' => $this->per_page,
            'post_type'     => $this->target_post_types,
			'post_status'   => 'publish',
			'orderby'       => [
				'date' => 'DESC',
			],
			'meta_query'    => [ // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => $dm->get_meta_name(),
					'value'   => sprintf( '"%s"', $id ),
					'compare' => 'REGEXP',
				],
			],
		];

		return $args;
	}

	/**
	 * クエリの上書き
	 *
	 * @param \WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_fujimain', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$allowed_origin_list = [
			'www.fujitv.co.jp',
			'www.check.fujitv.co.jp',
			'pccms.fujitv.co.jp',
		];

		$request_headers = [];
		foreach ( $_SERVER as $key => $value ) {
			if ( strpos( $key, 'HTTP_' ) !== false ) {
				$key = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
				$request_headers[ $key ] = $value;
			} else {
				$request_headers[ $key ] = $value;
			}
		}

		if ( isset( $request_headers['Origin'] ) ) {
			foreach ( $allowed_origin_list as $allowed_origin ) {
				if ( preg_match( sprintf( '@^https?://%s@', $allowed_origin ), $request_headers['Origin'], $matche ) ) {
					header( "Access-Control-Allow-Origin: {$matche[0]}" );
					break;
				}
			}
		}

		$this->xml_header();

		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:wfw="http://wellformedweb.org/CommentAPI/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
			xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php wp_title_rss(); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<lastBuildDate><?php echo $this->get_feed_build_date( 'r' ); ?></lastBuildDate>
			<language><?php bloginfo_rss( 'language' ); ?></language>
			<sy:updatePeriod>
			<?php
				$duration = 'hourly';
				echo apply_filters( 'rss_update_period', $duration );
			?>
			</sy:updatePeriod>
			<sy:updateFrequency>
			<?php
				$frequency = '1';
				echo apply_filters( 'rss_update_frequency', $frequency );
			?>
			</sy:updateFrequency>
			<?php
			do_action( 'rss_add_channel', [ $this, 'rss_add_channel' ] );

			while ( have_posts() ) :
				the_post();
				$this->render_item( get_post() );
				?>
			<?php endwhile; ?>
		</channel>
		</rss>
		<?php
	}

	/**
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	protected function render_item( $post ) {
		$description = apply_filters( 'the_excerpt_rss', get_the_excerpt() );
		$content     = get_the_content_feed( 'rss2' );
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		$thumbnail_url = '';
		$thumbnail_title = '';

		if ( $thumbnail_id ) {
			$thumbnail_url = get_the_post_thumbnail_url();
			$thumbnail_title = get_post( $thumbnail_id )->post_title;
		}
		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
				<dc:creator><![CDATA[<?php the_author(); ?>]]></dc:creator>
				<?php the_category_rss( 'rss2' ); ?>
				<guid isPermaLink="false"><?php the_guid(); ?></guid>
				<description><![CDATA[<?php echo $description; ?>]]></description>
				<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
				<?php if ( $thumbnail_id ) : ?>
					<image>
						<url><?php echo convert_chars( $thumbnail_url ); ?></url>
						<title><?php echo esc_html( convert_chars( $thumbnail_title ) ); ?></title>
						<link><?php the_permalink_rss(); ?></link>
					</image>
				<?php endif; ?>
				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>
			</item>
		<?php
	}

}
