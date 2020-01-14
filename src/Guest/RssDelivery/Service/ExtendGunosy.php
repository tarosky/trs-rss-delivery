<?php
/**
 * Gunosy Feed
 *
 * @package Guest
 */

namespace Guest\RssDelivery\Service;

use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Gunosy;
use WP_Query;

/**
 * Gunosy用RSS
 */
class ExtendGunosy extends Gunosy {
    protected $id = 'gunosy';
    protected $label = 'Gunosy';
    protected $target_post_types = [ 'news', 'restaurant', 'hyakusai', 'kojocho', 'column', 'yahoo'];

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'gunosy',
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

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_gunosy', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
        global $wp_query;
        global $post;

		$this->xml_header();
		do_action( 'rss_tag_pre', 'rss2' );

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

		$logo_url      = $this->get_logo_url();
		$wide_logo_url = $this->get_logo_url( true );
		?>
		<rss version="2.0"
			xmlns:gnf="http://assets.gunosy.com/media/gnf"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:media="http://search.yahoo.com/mrss/"
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
					<gnf:wide_image_link>
						<?php echo esc_url( $wide_logo_url ); ?>
					</gnf:wide_image_link>
				<?php endif; ?>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<lastBuildDate><?php echo $this->get_feed_build_date( 'r' ); ?></lastBuildDate>
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
		$content  = get_the_content_feed( 'rss2' );
		$status   = $post->post_status === 'publish' ? 'active' : 'deleted';
		$keywords = '';
		$tags     = get_the_tags();
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$keywords .= $keywords ? ',' : '';
				$keywords .= $tag->name;
			}
		}

        $thumbnail_url = $mime_type = '';
        $images = get_post_meta( $post->ID, '_images', true );
        if ( $images ) {
            $image = current( $images );
            if ( ! empty( $image['url'] ) ) :
                $thumbnail_url = $image['url'];
            elseif ( ! empty( $image['file'] ) ) :
                $thumbnail_url = $image['file'];
            endif;

            $img_data = @file_get_contents( $thumbnail_url );
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime_type = finfo_buffer( $finfo, $img_data );
            finfo_close( $finfo );
        } else {
            if ( has_post_thumbnail() ) {
                $thumbnail_url = get_the_post_thumbnail_url( $post, 'full' );
                $thumbnail_id = get_post_thumbnail_id( $post->ID );
                $mime_type = get_post_mime_type( $thumbnail_id );
            }
        }

        $related_field = '_related_links';
        if ( $post->post_type === 'yahoo' ) {
            $related_field = '_related_rss_links';
        }

        $related_links = get_post_meta( get_the_ID(), $related_field, true );
        $related_count = 0;

        $link = get_the_permalink();
        if ( $post->post_type === 'yahoo' ) {
            if ( $base_url = get_post_meta( get_the_ID(), '_base_url', true ) ) {
                $link = $base_url;
            }
        }
		?>
		<item>
			<title><?php the_title_rss(); ?></title>
            <link><?php echo esc_url( $link ); ?></link>
            <guid isPermaLink="false"><?php the_guid(); ?></guid>
            <gnf:category>economy</gnf:category>
			<?php if ( $keywords ) : ?>
				<gnf:keyword><?php echo esc_html( $keywords ); ?></gnf:keyword>
			<?php endif; ?>
			<description><![CDATA[<?php echo $content; ?>]]></description>
			<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
            <media:status state="<?php echo $status; ?>" />
			<pubDate><?php echo $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></pubDate>
			<gnf:modified><?php echo $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ?></gnf:modified>
            <?php if ( $thumbnail_url ) : ?>
                <enclosure url="<?= $thumbnail_url; ?>" type="<?= $mime_type; ?>" length="0" />
            <?php endif; ?>
            <?php if ( $related_links ) : ?>
                <?php foreach ( $related_links as $link ) : ?>
                    <?php
                        $related_url = $link['url'];
                        $related_title = $link['title'];

                        if ( $related_url && $related_title ) :
                            if ( $related_count >= 3 ) {
                                break;
                            }
                            $related_count++;
                    ?>
                        <gnf:relatedLink link="<?php echo esc_url( $related_url ); ?>" title="<?php echo esc_attr( $related_title ); ?>" />
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
			<?php
			do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
			?>

		</item>

		<?php
	}

    /**
     * 設定したカスタムロゴURLを返す
     *
     * @param bool $is_rectangle Whether it is rectangular.
     * @return string $logo_url ロゴURL.
     */
    public function get_logo_url( $is_rectangle = false ) {
        $logo_url = '';

        if( function_exists( 'trs_rss_get_logo' ) ) {
            if ( $is_rectangle ) {
                $logo_url = trs_rss_get_logo( 'Gunosy', true );
            } else {
                $logo_url = trs_rss_get_logo( 'Gunosy', false );
            }
        }

        return $logo_url;
    }

}
