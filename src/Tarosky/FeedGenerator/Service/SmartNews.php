<?php
/**
 * SmartNews Feed
 *
 * @package Guest
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * SmartNews用RSS
 */
class SmartNews extends AbstractFeed {

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'smartnews',
			'posts_per_rss' => $this->per_page,
            'post_type'     => $this->target_post_types,
			'post_status'   => [ 'publish', 'trash' ],
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

		/**
		 * ITEM
		 */
		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_smartnews', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$this->xml_header();

        $date = $this->to_local_time( date('Y-m-d H:i:s'), 'r', 'Asia/Tokyo' );
		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:media="http://search.yahoo.com/mrss/"
			xmlns:snf="http://www.smartnews.be/snf"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php wp_title_rss(); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<pubDate><?php echo $date; ?></pubDate>
			<language><?php bloginfo_rss( 'language' ); ?></language>
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
		$content       = get_the_content_feed( 'rss2' );
		$thumbnail_url = get_the_post_thumbnail_url();
		$status        = $post->post_status === 'publish' ? 'active' : 'deleted';
		$categories    = get_the_category();
		$cat_name_list = [];
		foreach ( $categories as $cat ) {
			$cat_name_list[] = $cat->name;
		}

		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<guid><?php the_permalink_rss(); ?></guid>
				<description><![CDATA[<?php echo $content; ?>]]></description>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
				<?php if ( count( $cat_name_list ) ) : ?>
					<category><?php echo implode( ',', $cat_name_list ); ?></category>
				<?php endif; ?>
				<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
				<dc:creator><?php the_author(); ?></dc:creator>
				<?php if ( $thumbnail_url ) : ?>
					<media:thumbnail url="<?php echo esc_url( $thumbnail_url ); ?>" />
				<?php endif; ?>
				<media:status><?php echo $status; ?></media:status>
				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}

}
