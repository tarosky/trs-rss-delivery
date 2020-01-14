<?php
/**
 * SmartNews Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\SmartNews;
use WP_Query;

/**
 * SmartNews用RSS
 */
class ExtendSmartNews extends SmartNews {
    /**
     * 記事ごとの表示確認識別ID.
     *
     * @var string $id 識別ID.
     */
    protected $id = 'smartnews';
    protected $label = 'SmartNews';

    protected $target_post_types = [ 'news', 'restaurant', 'hyakusai', 'kojocho', 'column', 'yahoo'];

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
        global $wp_query;
        global $post;

		$this->xml_header();

        $dm = DeliveryManager::instance();
        $id = $this->get_id();
        $shortage_posts = $wp_query->get_posts();
        $columns = get_posts( [
            'post_type' => 'column',
            'posts_per_page' => $this->per_page,
            'post_status'   => [ 'publish', 'trash' ],
            'meta_query'    => [
                [
                    'key'     => $dm->get_meta_name(),
                    'value'   => sprintf( '"%s"', $id ),
                    'compare' => 'REGEXP',
                ],
            ]
        ] );

        $yahoos = get_posts( [
            'post_type' => 'yahoo',
            'posts_per_page' => $this->per_page,
            'post_status'   => [ 'publish', 'trash' ],
            'meta_query'    => [
                [
                    'key'     => $dm->get_meta_name(),
                    'value'   => sprintf( '"%s"', $id ),
                    'compare' => 'REGEXP',
                ],
            ]
        ] );

        $all_posts = array_merge( $shortage_posts, $columns, $yahoos );
        $sort_keys = [];
        foreach($all_posts as $key => $value)
        {
            $sort_keys[$key] = $value->post_date;
        }
        array_multisort($sort_keys, SORT_DESC, $all_posts);
        $all_posts = array_slice( $all_posts, 0, $this->per_page );

        $logo_url = '';
        if( function_exists( 'trs_rss_get_logo' ) ) {
            $logo_url = trs_rss_get_logo( 'SmartNews' );
        }
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
            <?php if ( $logo_url ) : ?>
            <snf:logo>
                <url><?php echo $logo_url; ?></url>
            </snf:logo>
            <?php endif; ?>
			<?php
			do_action( 'rss_add_channel', [ $this, 'rss_add_channel' ] );

/*
            while ( have_posts() ) :
                the_post();
                $this->render_item( get_post() );
            endwhile;
*/
            foreach( $all_posts as $post ) :
                setup_postdata( $post );
                $this->render_item( $post );
            endforeach;
            wp_reset_postdata();
        ?>

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

        $thumbnail_url = '';
        $images = get_post_meta( $post->ID, '_images', true );
        if ( $images ) {
            $image = current( $images );
            if ( ! empty( $image['url'] ) ) :
                $thumbnail_url = $image['url'];
            elseif ( ! empty( $image['file'] ) ) :
                $thumbnail_url = $image['file'];
            endif;
        } else {
            if ( has_post_thumbnail() ) {
                $thumbnail_url = get_the_post_thumbnail_url( $post, 'full' );
            }
        }

		$status        = $post->post_status === 'publish' ? 'active' : 'deleted';
		$categories    = get_the_category();
		$cat_name_list = [];
		foreach ( $categories as $cat ) {
			$cat_name_list[] = $cat->name;
		}

        $author_name = '';
		if ( function_exists( 'paidmedia_get_the_post_author_name' ) ) {
            $author_name = paidmedia_get_the_post_author_name();
        }

        $guid = get_the_guid();
        if ( $post->post_type === 'yahoo' ) {
            if ( $base_url = get_post_meta( get_the_ID(), '_base_url', true ) ) {
                $guid = $base_url;
            }
        }
		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
                <guid><?php echo esc_url($guid); ?></guid>
				<description><![CDATA[<?php echo $content; ?>]]></description>
				<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
				<?php if ( count( $cat_name_list ) ) : ?>
					<category><?php echo implode( ',', $cat_name_list ); ?></category>
				<?php endif; ?>
				<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
                <?php if( $author_name ) : ?>
                    <dc:creator><?php echo esc_html( $author_name ); ?></dc:creator>
                <?php endif; ?>
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
