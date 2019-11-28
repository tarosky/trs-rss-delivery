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
    protected $target_post_types = [ 'news', 'restaurant', 'hyakusai', 'kojocho', 'column'];

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

        $all_posts = array_merge( $shortage_posts, $columns );
        $sort_keys = [];
        foreach($all_posts as $key => $value)
        {
            $sort_keys[$key] = $value->post_date;
        }
        array_multisort($sort_keys, SORT_DESC, $all_posts);
        $all_posts = array_slice( $all_posts, 0, $this->per_page );

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
        $content = get_the_content_feed( 'rss2' );
//        $content = str_replace( array("\r", "\n"), '', $content);

        // twitterやinstagramのブロック引用を残す
        $content = preg_replace_callback( '#<blockquote([^>]*?)>(.*?)</blockquote>#us', function($matches) {
            if ( false !== strpos( $matches[1], 'twitter' ) ) {
                return $matches[0];
            } elseif ( false !== strpos( $matches[1], 'instagram-media' ) ) {
                return $matches[0];
            } else {
                return '';
            }
        }, $content );
        // twitterやinstagramのscriptを残す
        $content = preg_replace_callback( '#<script([^>]*?)(.*?)</script>#us', function($matches) {
            if ( false !== strpos( $matches[0], 'platform.twitter.com' ) ) {
                return $matches[0];
            } elseif ( false !== strpos( $matches[0], 'platform.instagram.com' ) ) {
                return $matches[0];
            } else {
                return '';
            }
        }, $content );
        // ショートコード消す
        $content = strip_shortcodes( $content );
        // OembedになりそうなURLだけの行を消す
        $content = implode( "\n", array_filter( explode( "\r\n", $content ), function( $row ) {
            return ! preg_match( '#^https?://[a-zA-Z0-9\.\-\?=_/]+$#', $row );
        } ) );
        // 3行空白が続いたら圧縮
        $content = preg_replace( '/\\n{3,}/', "\n\n", $content );

//        $content = strip_tags( nl2br( $content ), '<h1><a><blockquote><iframe><script>' );
        $content = strip_tags( nl2br( $content ), '<h1><a><img><blockquote><iframe><script>' );

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
        } else {
            if ( has_post_thumbnail() ) {
                $thumbnail_url = get_the_post_thumbnail_url( $post, 'full' );
                $thumbnail_id = get_post_thumbnail_id( $post->ID );
                $mime_type = get_post_mime_type( $thumbnail_id );
            }
        }

        $related_links = get_post_meta( get_the_ID(), '_related_links', true );
        $related_count = 0;
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

                <?php if ( $related_links ) : ?>
                    <?php foreach ( $related_links as $link ) : ?>
                        <?php
                        $related_url = $link['url'];
                        $related_title = $link['title'];

                        if ( $related_url && $related_title ) :
                            if ( $related_count >= 5 ) {
                                break;
                            }
                            $related_count++;
                            ?>
                            <oa:reflink>
                                <oa:refTitle><![CDATA[<?php echo esc_attr( $related_title ); ?>]]></oa:refTitle>
                                <oa:refUrl><?php echo esc_url( $related_url ); ?></oa:refUrl>
                            </oa:reflink>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}

}
