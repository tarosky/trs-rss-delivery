<?php
/**
 * Ballooon Feed
 *
 * @package Guest
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * Ballooon用RSS
 */
class Ballooon extends AbstractFeed {

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'ballooon',
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

		add_action( 'rss_add_channel', function() {
			$title = apply_filters( 'wp_title_rss', get_wp_title_rss() );
			echo <<<EOM
	<copyright>{$title}</copyright>
EOM;
		} );

		/**
		 * ITEM
		 */
		add_action( 'rss2_item', function() {
			echo <<<EOM
	<category>ニュース</category>
EOM;
		} );

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_ballooon', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$this->xml_header();
		do_action( 'rss_tag_pre', 'rss2' );

		$logo_url      = $this->get_logo_url();
		$wide_logo_url = $this->get_logo_url();

		?>
		<rss version="2.0"
			xmlns:blf="https://www.ballooon.jp/media/blf/"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php wp_title_rss(); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<?php if ( $logo_url ) : ?>
				<image>
					<url><?php echo esc_url( $logo_url ); ?></url>
					<title><?php wp_title_rss(); ?></title>
					<link><?php bloginfo_rss( 'url' ); ?></link>
				</image>
			<?php endif; ?>
			<?php if ( $wide_logo_url ) : ?>
				<blf:wide_image_link><?php echo esc_url( $wide_logo_url ); ?></blf:wide_image_link>
			<?php endif; ?>
			<language><?php bloginfo_rss( 'language' ); ?></language>
			<lastBuildDate><?php echo $this->get_feed_build_date( 'r' ); ?></lastBuildDate>

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
		$description         = apply_filters( 'the_excerpt_rss', get_the_excerpt() );
		$trimmed_description = mb_strimwidth( $description, 0, 253, '...' );
		$content             = get_the_content_feed( 'rss2' );
		$status              = $post->post_status === 'publish' ? 'active' : 'deleted';
		$keywords            = '';
		$tags                = get_the_tags();
		if ( $tags ) {
			$count = 0;
			foreach ( $tags as $tag ) {
				$count++;
				$keywords .= $keywords ? ',' : '';
				$keywords .= $tag->name;
				if ( $count >= 10 ) {
					break;
				}
			}
		}
		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<guid><?php the_guid(); ?></guid>
				<?php if ( $keywords ) : ?>
					<blf:keyword><?php echo esc_html( $keywords ); ?></blf:keyword>
				<?php endif; ?>
				<description><![CDATA[<?php echo $trimmed_description; ?>]]></description>
				<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
				<blf:status><?php echo $status; ?></blf:status>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
				<dc:creator><?php the_author(); ?></dc:creator>
				<blf:modified><?php echo $this->to_local_time( get_the_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></blf:modified>
				<?php rss_enclosure(); ?>
				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>
			</item>
		<?php
	}

}
