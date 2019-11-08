<?php
/**
 * LINE Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Line;
use WP_Query;

/**
 * LINE用RSS
 */
class ExtendLine extends Line {
    protected $id = 'line';
    protected $label = 'Line';
    protected $target_post_types = [ 'column', 'news', 'restaurant', 'hyakusai', 'kojocho'];

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'line',
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
                [
                    'key'     => '_is_free',
                    'value'   => 'null',
                    'compare' => '!=',
                ],
            ],
            'tax_query'     => [
                [
                    'taxonomy' => 'news-cat',
                    'field'    => 'slug',
                    'terms'    => '08',
                    'operator' => 'NOT IN',
                ]
            ],
		];

		return $args;
	}

	/**
	 * クエリの上書き
	 *
	 * @param WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {

		/**
		 * ITEM
		 */
		// guidタグ内容を編集.
		add_filter( 'the_guid', function( $guid, $id ) {
			return get_permalink( $id );
		}, 10, 2 );

		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_line', [ $this, 'do_feed' ] );
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
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><![CDATA[<?php wp_title_rss(); ?>]]></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><![CDATA[<?php bloginfo_rss( 'description' ); ?>]]></description>
			<lastBuildDate><?php echo $this->get_feed_build_date( 'r' ); ?></lastBuildDate>
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
		$content = get_the_content_feed( 'rss2' );
		$status  = $post->post_status === 'publish' ? '2' : '0';

        $thumbnail_url = $mime_type = '';
        $images = get_post_meta( $post->ID, '_images', true );
        if ( $images ) {
            $image = current( $images );
            if ( ! empty( $image['url'] ) ) :
                $thumbnail_url = $image['url'];
            elseif ( ! empty( $image['file'] ) ) :
                $thumbnail_url = $image['file'];
            endif;

            $img_data = file_get_contents( $thumbnail_url );
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime_type = finfo_buffer( $finfo, $img_data );
            finfo_close( $finfo );
        }
		?>
			<item>
				<guid><?php the_guid(); ?></guid>
				<title><![CDATA[<?php the_title_rss(); ?>]]></title>
				<link><?php the_permalink_rss(); ?></link>
				<description><![CDATA[<?php echo $content; ?>]]></description>
                <?php if ( $thumbnail_url ) : ?>
                    <enclosure url="<?= $thumbnail_url; ?>" type="<?= $mime_type; ?>" />
                <?php endif; ?>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>

				<oa:lastPubDate><?php echo $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></oa:lastPubDate>
				<oa:pubStatus><?php echo $status; ?></oa:pubStatus>

				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}

}
