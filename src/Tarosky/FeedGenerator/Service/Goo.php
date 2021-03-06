<?php
/**
 * Goo Feed
 *
 * @package Guest
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * Goo用RSS
 */
class Goo extends AbstractFeed {

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'goo',
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
		add_action( 'rss2_item', function() {
			$categories = get_the_category();

			$category = 'region';
			if ( $categories ) :
				$category = esc_html( $categories[0]->name );
			endif;

			echo <<<EOM
	<category>{$category}</category>
EOM;
		} );

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_goo', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$this->xml_header();

		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:oa="http://news.line.me/rss/1.0/oa"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:goonews="http://news.goo.ne.jp/rss/2.0/news/goonews/"
			xmlns:smp="http://news.goo.ne.jp/rss/2.0/news/smp/"

			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php wp_title_rss(); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<language>ja</language>
			<pubDate><?php echo $this->get_feed_build_date( 'r' ); ?></pubDate>
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
	 * @param WP_Post $post 投稿記事.
	 */
	protected function render_item( $post ) {
		$content    = strip_tags( get_the_content_feed( 'rss2' ), '<h2><p><br><img>' );
		$guid       = sprintf( '%s-%d', get_the_date( 'Ymd' ), get_the_ID() );
		?>
			<item>
				<guid isPermaLink="false"><?php echo $guid; ?></guid>
				<?php if ( $post->post_status === 'trash' ) : ?>
					<goonews:delete>1</goonews:delete>
				<?php endif; ?>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
				<goonews:modified><?php echo $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></goonews:modified>
				<dc:creator><?php the_author(); ?></dc:creator>
				<description><![CDATA[<?php echo $content; ?>]]></description>
				<?php rss_enclosure(); ?>
				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}

}
