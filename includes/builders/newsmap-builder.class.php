<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class NewsmapBuilder extends GoogleSitemapBuilder {
    /**
     * @since 6.0
     */
    const SITEMAP_SLUG = 'newsmap';

    /**
     * @since 5.0
     * @var array
     */
    private $rawQueriedPosts;

    /**
     * @since 5.0
     *
     * @param object $plugin
     * @param object $delegate
     */
    public function __construct( $plugin, $delegate ) {
        parent::__construct( $plugin, $delegate );

        $this->publicationLanguage = $this->db->getOption( 'publication_lang' );
        $this->publicationName     = $this->prepareAttribute( $this->db->getOption( 'publication_name' ) );

        if (! preg_match( '/^[a-z]{2}-?[a-z]{1,2}$/', $this->publicationLanguage ) ) {
            $this->publicationLanguage = 'eng';
        }
    }

    /**
     * @see parent::runBuildingProcess()
     * @since 5.0
     */
    protected function runBuildingProcess() {
        if (! $this->queryPosts() ) {
            return false;
        }

        foreach ( $this->rawQueriedPosts as $post ) {
            $post = sanitize_post( $post, 'raw' );

            wp_cache_add( $post->ID, $post, 'posts' );

            $this->buildURLElement( $post );
        }

        unset( $this->rawQueriedPosts );

        return true;
    }

    /**
     * @since 5.0
     * @param object $post
     */
    public function buildURLElement( $post ) {
        $this->incrementItemsCounter();

        $this->output .= '<url>' . $this->lineBreak
                       . '<loc>' . get_permalink( $post ) . '</loc>' . $this->lineBreak
                       . '<news:news>' . $this->lineBreak . '<news:publication>' . $this->lineBreak
                       . '<news:name>' . $this->publicationName . '</news:name>' . $this->lineBreak
                       . '<news:language>' . $this->publicationLanguage . '</news:language>' . $this->lineBreak
                       . '</news:publication>' . $this->lineBreak
                       . '<news:title>' . $this->prepareAttribute( $post->post_title ) . '</news:title>' . $this->lineBreak 
                       . '<news:publication_date>' . gmdate( 'Y-m-d\TH:i:s', strtotime( $post->post_date ) )
                       . $this->timezoneOffset . '</news:publication_date>' . $this->lineBreak
                       . '</news:news>' . $this->lineBreak 
                       . '</url>' . $this->lineBreak;
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function queryPosts() {
        $post_type_to_include = $this->indexer->getRequestedSitemapContentType();
        $meta_key             = $this->db->prepareMetaKey( 'exclude_from_newsmap' );

        $query_clauses = array(
            'SELECT'          => 'p.ID, p.post_name, p.post_date, p.post_title, p.post_parent, p.post_type, p.post_status',
            'FROM'            => "{$this->wpdb->posts} AS p",
            'LEFT_OUTER_JOIN' => "{$this->wpdb->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = {$meta_key}",
            'WHERE'           => "p.post_type = '{$post_type_to_include}' AND 
                                  ( p.post_date_gmt >= UTC_TIMESTAMP() - INTERVAL 2 DAY ) AND
                                  p.post_status = 'publish' AND p.post_password = '' AND pm.post_id IS NULL",
            'ORDER_BY'        => 'p.post_date DESC',
            'LIMIT'           => $this->buildingCapacityLeft(),
            'OFFSET'          => $this->getMysqlOffset()
        );

        /**
         * @since 5.3
         */
        $query_clauses = apply_filters( 'sitetree_newsmap_builder_posts_query', $query_clauses, $post_type_to_include );
        
        $this->rawQueriedPosts = $this->db->getResults( $query_clauses );

        return (bool) $this->rawQueriedPosts;
    }
}
?>