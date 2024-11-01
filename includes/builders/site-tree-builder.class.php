<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 2.0
 */
final class SiteTreeBuilder
    extends BuilderCore
 implements SiteTreeBuilderInterface {
    /**
     * @since 6.0
     */
    const SITEMAP_SLUG = 'site_tree';

    /**
     * @since 6.0
     */
    const STD_ITEMS_LIMIT = 1000;

    /**
     * @since 6.0
     * @var object
     */
    private $paginator;

    /**
     * @since 3.3.2
     * @var string
     */
    private $tempOutput = '';

    /**
     * @since 2.0
     * @var array
     */
    private $listOptions;

    /**
     * @since 2.0
     * @var string
     */
    private $listID = '';

    /**
     * @since 5.0
     * @var int
     */
    private $limit;

    /**
     * @since 6.0
     * @var int
     */
    private $offset;

    /**
     * @since 5.0
     * @var array
     */
    private $queryClauses = array();

    /**
     * @since 5.0
     * @var array
     */
    private $queryResults;

    /**
     * @since 2.0
     * @var array
     */
    private $methodsDictionary;

    /**
     * @since 5.3
     * @var array
     */
    private $contentTypes = array(
        'post'     => 'post',
        'page'     => 'post',
        'post_tag' => 'taxonomy',
        'category' => 'taxonomy',
        'authors'  => 'author'
    );

    /**
     * @since 3.3.2
     * @var bool
     */
    private $encloseList = true;

    /**
     * @since 4.5
     * @var bool
     */
    private $doingShortcode = false;

    /**
     * @since 5.2
     * @var bool
     */
    private $doingHyperlist = false;

    /**
     * @since 6.0
     * @param object $paginator
     */
    public function setPaginator( $paginator ) {
        $this->paginator = $paginator;
    }

    /**
     * {@inheritdoc}
     */
    public function listID() {
    	return $this->listID;
    }

    /**
     * {@inheritdoc}
     */
    public function addContent( $string ) {
    	$this->output .= $string;
    }

    /**
     * @since 5.3
     * @deprecated Since version 6.0
     *
     * @return string
     */
    public function getContentTypeID() {
         _deprecated_function( __METHOD__, '6.0', 'SiteTreeBuilder::getContentTypeFamily()' );

        return $this->getContentTypeFamily();
    }

    /**
     * {@inheritdoc}
     */
    public function getContentTypeFamily() {
        return $this->contentTypes[$this->listID];
    }

    /**
     * @since 5.0
     * @param string $string
     */
    public function addTempContent( $string ) {
        $this->tempOutput .= $string;
    }

    /**
     * @since 4.5
     * @param bool $true_or_false
     */
    public function setDoingShortcode( $true_or_false ) {
        $this->doingShortcode = $true_or_false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDoingShortcode() {
        return $this->doingShortcode;
    }

    /**
     * @since 5.2
     * @param bool $true_or_false
     */
    public function setDoingHyperlist( $true_or_false ) {
        $this->doingHyperlist = $true_or_false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDoingHyperlist() {
        return $this->doingHyperlist;
    }

    /**
     * @since 2.0
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getListOption( $key, $default = false ) {
        if ( isset( $this->listOptions[$key] ) ) {
            switch ( $this->listOptions[$key] ) {
                case 'true':
                    return true;

                case 'false':
                    return false;

                default:
                    return $this->listOptions[$key];
            } 
        }

        return $default;
    }

    /**
     * @since 5.0
     * @return string
     */
    public function getQueryClause( $clause_id ) {
        return ( isset( $this->queryClauses[$clause_id] ) ? $this->queryClauses[$clause_id] : '' );
    }

    /**
     * @since 5.0
     *
     * @param string $clause_id
     * @param string $clause
     */
    public function setQueryClause( $clause_id, $clause ) {
        if (! isset( $this->queryClauses[$clause_id] ) ) {
            $this->queryClauses[$clause_id] = '';
        }

        $this->queryClauses[$clause_id] = $clause;
    }

    /**
     * @since 5.0
     *
     * @param string $clause_id
     * @param string $clause_tail
     */
    public function appendToQueryClause( $clause_id, $clause_tail ) {
        if (! isset( $this->queryClauses[$clause_id] ) ) {
            $this->queryClauses[$clause_id] = '';
        }

        $this->queryClauses[$clause_id] .= ' ' . $clause_tail;
    }

    /**
     * @since 5.0
     *
     * @param string $clause_id
     * @param string $clause
     */
    public function prependToQueryClause( $clause_id, $clause ) {
        if (! isset( $this->queryClauses[$clause_id] ) ) {
            $this->queryClauses[$clause_id] = '';
        }

        $this->queryClauses[$clause_id] = $clause . ' ' . $this->queryClauses[$clause_id];
    }

    /**
     * @since 5.3
     * @return string
     */
    public function getTheQuery() {
        $group_by = '';

        if ( $this->queryClauses['group_by'] ) {
            $group_by = 'GROUP BY ' . $this->queryClauses['group_by'];
        }

        return "SELECT {$this->queryClauses['fields']}
                FROM {$this->queryClauses['from']}
                {$this->queryClauses['joins']}
                WHERE {$this->queryClauses['where']}
                {$group_by}
                ORDER BY {$this->queryClauses['order_by']}
                LIMIT {$this->queryClauses['limit']}
                OFFSET {$this->queryClauses['offset']}";
    }

    /**
     * @since 5.0
     * @return array
     */
    public function &getQueryResults() {
        return $this->queryResults;
    }

    /**
     * @since 5.0
     */
    private function resetQueryData() {
        $this->limit        = self::STD_ITEMS_LIMIT;
        $this->offset       = 0;
        $this->queryResults = array();
        $this->queryClauses = array(
            'fields'   => '',
            'from'     => '',
            'where'    => '',
            'joins'    => '',
            'order_by' => '',
            'group_by' => '',
            'limit'    => self::STD_ITEMS_LIMIT,
            'offset'   => '0'
        );
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function queryDB() {
        /**
         * @since 5.0
         */
        do_action( 'sitetree_builder_will_query_db', $this );

        $this->queryResults = $this->wpdb->get_results( $this->getTheQuery() );

        return (bool) $this->queryResults;
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function init() {
        if ( $this->methodsDictionary ) {
            return false;
        }

        $this->methodsDictionary = array(
            'post'     =>  'buildPostsList',
            'page'     =>  'buildPagesList',
            'category' =>  'buildCustomTaxonomiesList',
            'post_tag' =>  'buildTagsList',
            'authors'  =>  'buildAuthorsList'
        );

        $post_types = get_post_types( array( 'public' => true, '_builtin' => false ) );
        $taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ) );

        foreach ( $post_types as $post_type ) {
            $this->contentTypes[$post_type]      = 'post';
            $this->methodsDictionary[$post_type] =  'buildCustomPostsList';
        }

        foreach ( $taxonomies as $taxonomy ) {
            $this->contentTypes[$taxonomy]      = 'taxonomy';
            $this->methodsDictionary[$taxonomy] =  'buildCustomTaxonomiesList';
        }

        $this->resetQueryData();

        return true;
    }

    /**
     * @since 4.0
     *
     * @param string $list_id
     * @param array $options
     * @return string
     */
    public function buildList( $list_id, $options ) {
        $this->init();

        if (! isset( $this->methodsDictionary[$list_id] ) ) {
            return '';
        }

        $this->listID      = $list_id;
        $this->listOptions = &$options;
        $this->output      = '';

        $this->runListBuildingProcess();

        $this->doingHyperlist = false;
        $this->doingShortcode = false;

        return $this->output;
    }

    /**
     * @since 4.5
     */
    private function runListBuildingProcess() {
        $method_name = $this->methodsDictionary[$this->listID];

        if ( $this->doingHyperlist ) {
            $limit = $this->getListOption( 'limit' );

            if ( ( $limit > 0 ) && ( $limit < self::STD_ITEMS_LIMIT ) ) {
                $this->limit = $limit;

                $this->setQueryClause( 'limit', $limit );
            } 
        }

        $this->output .= '<div class="site-tree-list-container">' . "\n";
            
        if ( $this->getListOption( 'show_title', true ) ) {
            $title = $this->getListOption( 'title' );

            if ( $title ) {
                /**
                 * @since 5.3
                 */
                $title = apply_filters( 'sitetree_builder_hyperlist_title', $title, $this );

                $this->output .= '<h3 id="site-tree-' . $this->listID 
                               . '-list-title" class="site-tree-list-title ">' . $title . "</h3>\n";
            } 
        }

        /**
         * @since 1.0
         */
        do_action( 'sitetree_will_build_single_list', $this );
        
        $this->{$method_name}();
        
        if ( $this->encloseList ) {
            $this->output .= '<ul id="site-tree-' . $this->listID 
                           . '-list" class="site-tree-list">' . "\n" . $this->tempOutput . "</ul>\n";
        }
        else {
            $this->output .= $this->tempOutput;
        }

        /**
         * @since 1.0
         */
        do_action( 'sitetree_did_build_single_list', $this );

        $this->output    .= "</div>\n";
        $this->tempOutput = '';

        $this->resetQueryData();
    }

    /**
     * @see parent::runBuildingProcess()
     * @since 2.0
     */
    protected function runBuildingProcess() {
        $content_types = $this->paginator->getContentTypesForRequestedPage();

        $this->init();

        /**
         * @since 2.1
         */
        do_action_deprecated( 'sitetree_will_build_lists', array( $this ), 'SiteTree 5.3', 'sitetree_will_build_site_tree' );

        /**
         * @since 5.3
         */
        do_action( 'sitetree_will_build_site_tree', $this, $this->paginator );

        $this->output .= '<div id="site-tree">' . "\n";

        foreach ( $content_types as $this->listID => $limiting_paramenters ) {
            if (! isset( $this->methodsDictionary[$this->listID] ) ) {
                continue;
            }

            $this->listOptions = $this->db->getOption( $this->listID, array(), self::SITEMAP_SLUG );

            if (! is_array(  $this->listOptions ) ) {
                continue;
            }

            $this->limit = $limiting_paramenters['limit'];
            $this->offset = $limiting_paramenters['offset'];

            $this->setQueryClause( 'limit', $this->limit );
            $this->setQueryClause( 'offset', $this->offset );

            $this->runListBuildingProcess();
        }

        $this->output .= $this->paginator->getNavigationMenu();
        $this->output .= "</div>\n";

        /**
         * @since 2.1
         */
        do_action_deprecated( 'sitetree_did_build_lists', array( $this ), 'SiteTree 5.3', 'sitetree_did_build_site_tree' );

        /**
         * @since 5.3
         */
        do_action( 'sitetree_did_build_site_tree', $this, $this->paginator );
    }

    /**
     * @since 5.2
     *
     * @param string $meta_keys
     * @param string $postmeta_conditions
     * @return bool
     */
    private function prepareVariablesOnDoingHyperlist( &$meta_keys, &$postmeta_conditions ) {
        if (! $this->doingHyperlist ) {
            return false;
        }

        $list_of_ids_to_include = $this->getListOption( 'include_only' );

        if ( $list_of_ids_to_include ) {
            $ids_to_include = wp_parse_id_list( $list_of_ids_to_include );

            if ( $ids_to_include ) {
                $list_of_ids_to_include = implode( ',', $ids_to_include );
                $ids_condition          = 'AND p.ID IN (' . $list_of_ids_to_include . ')';
                
                $this->appendToQueryClause( 'where', $ids_condition );
            }

            return true;
        }

        $list_of_ids_to_exclude = $this->getListOption( 'exclude' );

        if ( $list_of_ids_to_exclude ) {
            $ids_to_exclude = wp_parse_id_list( $list_of_ids_to_exclude );

            if ( $ids_to_exclude ) {
                $list_of_ids_to_exclude = implode( ',', $ids_to_exclude );
                $ids_condition          = 'AND p.ID NOT IN (' . $list_of_ids_to_exclude . ')' ;

                $this->appendToQueryClause( 'where', $ids_condition );
            }
        }

        if ( $this->doingShortcode ) {
            $list_of_ids_globally_excluded = $this->getListOption( 'include_globally_excluded' );

            if ( $list_of_ids_globally_excluded ) {
                if ( $list_of_ids_globally_excluded === true ) {
                    return true;
                }

                $ids_to_include = wp_parse_id_list(  $list_of_ids_globally_excluded );

                if ( $ids_to_include ) {
                    $list_of_ids_globally_excluded = implode( ',', $ids_to_include );
                    $postmeta_conditions .= ' AND pm.post_id NOT IN (' .  $list_of_ids_globally_excluded . ')' ;
                }
            }
            
            $meta_keys .= ',';
            $meta_keys .= $this->db->prepareMetaKey( 'exclude_from_shortcode_lists' );  
        }

        return true;
    }

    /**
     * @since 2.0
     * @return bool
     */
    private function buildPagesList() {
        if (! $this->queryPages() ) {
            return false;
        }

        $progenitor_page_id = $this->getListOption( 'only_children_of' );

        if ( is_numeric( $progenitor_page_id ) && ( $progenitor_page_id > 0 ) ) {
            $this->queryResults = get_page_children( $progenitor_page_id, $this->queryResults );
        }

        if ( !$this->getListOption( 'exclude_children' ) && $this->getListOption( 'hierarchical', true ) ) {
            $list_depth = 0;
        }
        else {
            $list_depth = -1;
        }

        $arguments = array( 'list_depth' => $list_depth );

        /**
         * @since 5.0
         */
        $can_build_list = apply_filters( 'sitetree_builder_can_build_pages_list', true, $this, $arguments );

        if ( $can_build_list ) {
            if ( $this->getListOption( 'show_home' ) && !get_option( 'page_on_front' ) ) {
                $this->tempOutput .= '<li><a href="' . home_url( '/' )
                                   . '">' . __( 'Home', 'sitetree' ) . '</a></li>';
            }

            $walker            = new PageWalker( $this );
            $this->tempOutput .= $walker->walk( $this->queryResults, $list_depth );
        }
        
        return true;
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function queryPages() {
        $fields              = 'p.ID, p.post_title, p.post_name';
        $postmeta_conditions = 'pm.meta_key IN (%s)';
        
        $order_by   = $this->getListOption( 'order_by', 'menu_order' );
        $meta_keys  = $this->db->prepareMetaKey( 'exclude_from_site_tree' );
        $meta_keys .= ',';
        $meta_keys .= $this->db->prepareMetaKey( 'is_ghost_page' );

        $this->appendToQueryClause( 'where', "p.post_type = 'page' AND p.post_status = 'publish' AND
                                              p.post_password = '' AND pm.post_id IS NULL" );

        if ( $this->getListOption( 'exclude_children' ) ) {
            $this->appendToQueryClause( 'where', 'AND p.post_parent = 0' );
        }
        else {
            $fields .= ', p.post_parent, p.post_type';
        }

        $page_on_front = (int) get_option( 'page_on_front' );

        if ( $page_on_front > 0 ) {
           $this->appendToQueryClause( 'order_by', "p.ID = {$page_on_front} DESC," ); 
        }

        if ( $order_by == 'title' ) {
            $this->appendToQueryClause( 'order_by', 'p.post_title ASC' );
        }
        else {
            $this->appendToQueryClause( 'order_by', 'p.menu_order, p.post_title ASC' );
        }

        $this->prepareVariablesOnDoingHyperlist( $meta_keys, $postmeta_conditions );

        $postmeta_conditions = sprintf( $postmeta_conditions, $meta_keys );

        $this->appendToQueryClause( 'fields', $fields );
        $this->appendToQueryClause( 'from', "{$this->wpdb->posts} AS p" );
        $this->appendToQueryClause( 'joins', "LEFT OUTER JOIN {$this->wpdb->postmeta} AS pm
                                              ON pm.post_id = p.ID AND {$postmeta_conditions}" );
        
        return $this->queryDB();
    }

    /**
     * @since 3.0
     */
    private function buildCustomPostsList() { 
        $fields              = 'p.ID, p.post_title, p.post_name, p.post_type';
        $postmeta_conditions = 'pm.meta_key IN (%s)';
        
        $list_depth = -1;
        $order_by   = $this->getListOption( 'order_by', 'post_title' );
        $meta_keys  = $this->db->prepareMetaKey( 'exclude_from_site_tree' );

        $this->appendToQueryClause( 'where', "p.post_type = '{$this->listID}' AND p.post_status = 'publish' AND 
                                              p.post_password = '' AND pm.post_id IS NULL" );
        
        if ( (bool) $this->getListOption( 'hierarchical', false ) ) {
            $list_depth = 0;
            $fields    .= ', p.post_parent';
        }

        switch ( $order_by ) {
            case 'post_date':
                $this->appendToQueryClause( 'order_by', 'p.post_date DESC' );
                break;
            case 'post_date_asc':
                $this->appendToQueryClause( 'order_by', 'p.post_date ASC' );
                break;
            default:
                $this->appendToQueryClause( 'order_by', 'p.post_title ASC' );
                break;
        }

        $this->prepareVariablesOnDoingHyperlist( $meta_keys, $postmeta_conditions );

        $postmeta_conditions = sprintf( $postmeta_conditions, $meta_keys );

        $this->appendToQueryClause( 'fields', $fields );
        $this->appendToQueryClause( 'from', "{$this->wpdb->posts} AS p" );
        $this->appendToQueryClause( 'joins', "LEFT OUTER JOIN {$this->wpdb->postmeta} AS pm
                                              ON pm.post_id = p.ID AND {$postmeta_conditions}" );

        if ( $this->queryDB() ) {
            $walker         = new CustomPostWalker( $this );
            $this->tempOutput .= $walker->walk( $this->queryResults, $list_depth );
        }
    }

    /**
     * @since 3.0
     */
    private function buildCustomTaxonomiesList() {
    	$arguments = array(
            'depth'         => -1,
            'exclude'       => $this->getListOption( 'exclude', '' ),
            'feed'          => '',
            'hide_empty'    => true,
            'hierarchical'  => (bool) $this->getListOption( 'hierarchical', true ),
            'orderby'       => $this->getListOption( 'order_by', 'name' ),
            'show_count'    => false,
            'number'        => $this->limit,
            'offset'        => $this->offset
		);

		if ( $arguments['orderby'] == 'count' ) {
            $arguments['order'] = 'DESC';
        }
        
        if ( $arguments['hierarchical'] ) {
            $arguments['depth']        = 0;
            $arguments['exclude_tree'] = $arguments['exclude'];
            $arguments['exclude']      = '';
        }

        if ( $this->listID == 'category' ) {
            $arguments['feed']       = $this->getListOption( 'feed_text', 'RSS' );
            $arguments['show_count'] = (bool) $this->getListOption( 'posts_count', true );
        }
        
        if (! $this->queryTerms( $arguments ) ) {
            return false;
        }

        $walker            = new TaxonomyWalker( $this );
        $this->tempOutput .= $walker->walk( $this->queryResults, $arguments['depth'], $arguments );
    }
    
    /**
     * @since 2.0
     */
    private function buildTagsList() {
        $arguments = array(
            'exclude' => $this->getListOption( 'exclude', array() ),
            'number'  => $this->limit,
            'offset'  => $this->offset
        );
        
        if ( $this->getListOption( 'order_by', 'name' ) != 'name' ) {
            $arguments['order']   = 'DESC';
            $arguments['orderby'] = $this->getListOption( 'order_by' );
        }

        if (! $this->queryTerms( $arguments ) ) {
            return false;
        }

        $show_count = $this->getListOption( 'show_count' );

        foreach ( $this->queryResults as $tag ) {
            $this->incrementItemsCounter();

            $stripped_tag_name = esc_attr( strip_tags( $tag->name ) );
            $link_title_format = __( 'View all posts tagged %s', 'sitetree' );

            $this->tempOutput .= '<li><a href="' . get_term_link( $tag ) . '" title="';
            $this->tempOutput .= sprintf( $link_title_format, $stripped_tag_name );
            $this->tempOutput .= '">' . esc_attr( $tag->name ) . '</a>';
            
            if ( $show_count && $tag->count > 1 ) {
                $this->tempOutput .= ' <span class="site-tree-posts-count">(';
                $this->tempOutput .= (int) $tag->count;
                $this->tempOutput .= ')</span>';
            }
            
            $this->tempOutput .= "</li>\n";
        }
    }

    /**
     * @since 5.0
     *
     * @param array $arguments
     * @return bool
     */
    private function queryTerms( $arguments ) {
        /**
         * @since 5.0
         */
        $arguments = apply_filters( 'sitetree_builder_arguments_to_query_terms', $arguments, $this );

        $this->queryResults = get_terms( $this->listID, $arguments );

        return (bool) $this->queryResults;
    }
    
    /**
     * @since 2.0
     */
    private function buildAuthorsList() {
        if (! $this->queryAuthors() ) {
            return false;
        }

        $show_bio         = $this->getListOption( 'show_bio' );
        $show_count       = $this->getListOption( 'show_count' );
        $show_avatar      = $this->getListOption( 'show_avatar' );
        $avatar_size      = (int) $this->getListOption( 'avatar_size', 60 );

        foreach ( $this->queryResults as $author ) {
            $this->incrementItemsCounter();

            $link_title_text      = __( 'View all posts by %s', 'sitetree' );
            $stripped_author_name = esc_attr( strip_tags( $author->display_name ) );
            
            $item  = '<a href="';
            $item .= get_author_posts_url( $author->ID, $author->user_nicename );
            $item .= '" class="p-name" title="';
            $item .= sprintf( $link_title_text, $stripped_author_name );
            $item .= '">' . esc_attr( $author->display_name ) . '</a>';
            
            if ( $show_count ) {
                $item .= ' <span class="site-tree-posts-count">(';
                $item .= (int) $author->posts_count;
                $item .= ')</span>';
            }
            
            if ( $show_bio && $author->bio_info ) {
               $item .= '<p class="p-note">' . $author->bio_info . '</p>';
            }
            
            if ( $show_avatar ) {
                $avatar = get_avatar( 
                    $author->user_email, 
                    $avatar_size, 
                    '',
                    $author->display_name,
                    array( 'class' => 'u-photo' )
                );
                $item = $avatar . $item;
            }

            $this->tempOutput .= '<li class="h-card">' . $item . "</li>\n";
        }
    }

    /**
     * @since 2.0
     */
    private function queryAuthors() {
        $fields = 'u.ID, COUNT(u.ID) AS posts_count, u.user_nicename, u.display_name';
        $joins  = "INNER JOIN {$this->wpdb->posts} AS p ON p.post_author = u.ID";
        $where  = "p.post_type = 'post' AND p.post_status = 'publish'";
        
        $excluded_authors = $this->getListOption( 'exclude' );

        if ( $excluded_authors ) {
            $excluded_authors_list = '';
            $excluded_authors      = explode( ',', $excluded_authors );

            foreach ( $excluded_authors as $author_nickname ) {
                $excluded_authors_list .= "'" . sanitize_text_field( $author_nickname ) . "',";
            }

            // Removes the trailing comma from the string.
            $excluded_authors_list = substr( $excluded_authors_list, 0, -1);
            $where                 = "u.user_nicename NOT IN ({$excluded_authors_list}) AND {$where}";
        }

        if ( $this->getListOption( 'show_avatar' ) )
            $fields .= ', u.user_email';
            
        if ( $this->getListOption( 'show_bio' ) ) {
            $fields .= ', um.meta_value AS bio_info';
            $where  .= " AND um.meta_key = 'description'";
            $joins  .= " LEFT JOIN {$this->wpdb->usermeta} AS um ON um.user_id = u.ID";
        }
        
        if ( $this->getListOption( 'order_by' ) == 'posts_count' ) {
            $this->appendToQueryClause( 'order_by', 'posts_count DESC' );
        }
        else {
            $this->appendToQueryClause( 'order_by', 'u.display_name ASC' );
        }

        $this->appendToQueryClause( 'fields', $fields );
        $this->appendToQueryClause( 'from', "{$this->wpdb->users} AS u" );
        $this->appendToQueryClause( 'joins', $joins );
        $this->appendToQueryClause( 'where', $where );
        $this->appendToQueryClause( 'group_by', 'u.ID' );
        
        return $this->queryDB();
    }
    
    /**
     * @since 2.0
     * @return bool
     */
    private function buildPostsList() {
        if (! $this->queryPosts() ) {
            return false;
        }

        if ( $this->getListOption( 'pop_stickies' ) ) {
            $this->popStickyPosts();
        }

        $current_value = $date = $day = '';
        
        $hierarchical           = true;
        $property               = 'ID';
        $date_format            = get_option( 'date_format' );
        $group_by                = sanitize_key( $this->getListOption( 'group_by' ) );
        $hyperlink_group_title  = (bool) $this->getListOption( 'hyperlink_group_title', true );
        $show_date              = (bool) $this->getListOption( 'show_date' );
        $show_excerpt           = (bool) $this->getListOption( 'show_excerpt' );
        $excerpt_length         = ( $show_excerpt ? (int) $this->getListOption( 'excerpt_length', 100 ) : 0 );
        $show_comments_count    = (bool) $this->getListOption( 'show_comments_count' );

        $this->encloseList = false;

        switch ( $group_by ) {
            case 'date':
                $property = 'post_month';
                break;
            case 'category':
                $property = 'term_id';
                break;
            case 'author':
                $property = 'post_author';
                break;
            default:
                $hierarchical      = false;
                $this->encloseList = true;
                break;
        }

        foreach ( $this->queryResults as $post ) {
            if ( $hierarchical && ( $post->{$property} != $current_value ) ) {
                $current_value = $post->{$property};
                $method_name   = 'getPostGroupTitle_' . $group_by;

                $this->tempOutput .= "</ul>\n<h4>";
                $this->tempOutput .= $this->{$method_name}( $post, $hyperlink_group_title );
                $this->tempOutput .= "</a></h4>\n<ul class=\"site-tree-list\">\n";
            }
            
            if ( $show_date ) {
                if ( $group_by == 'date' ) {
                    $day  = '<time datetime="';
                    $day .= mysql2date( 'Y-m-d', $post->post_date ) . '">';
                    $day .= mysql2date( 'd:', $post->post_date ) . '</time> ';
                }
                else {
                    $date  = ' <time datetime="';
                    $date .= mysql2date( 'Y-m-d', $post->post_date ) . '">';
                    $date .= mysql2date( $date_format, $post->post_date ) . '</time>';
                }
            }
            
            $this->tempOutput .= '<li>' . $day . '<a href="';
            $this->tempOutput .= get_permalink( $post ) . '">';
            $this->tempOutput .= apply_filters( 'the_title', $post->post_title, $post->ID );
            $this->tempOutput .= '</a>';
                    
            if ( $show_comments_count && $post->comment_count > 0 ) {
                $this->tempOutput .= ' <span class="site-tree-comments-number">(';
                $this->tempOutput .= (int) $post->comment_count;
                $this->tempOutput .= ')</span>';
            }
                
            $this->tempOutput .= $date;

            if ( $show_excerpt ) {
            	$excerpt = ( $post->post_excerpt ? $post->post_excerpt : $post->post_extract );
            	$excerpt = strip_tags( $excerpt );

            	if ( $excerpt ){
            		$this->tempOutput .= '<p>';
	            	$this->tempOutput .= \sitetree_fn\truncate_sentence( $excerpt, $excerpt_length );
	            	$this->tempOutput .= '</p>';
            	}
            }

            $this->tempOutput .= "</li>\n";
            
            $this->incrementItemsCounter();
        }

        return true;
    }

    /**
     * @since 2.0
     */
    private function queryPosts() {
        $joins    = '';
        $order_by = $this->getListOption( 'order_by', 'post_date' );

        $postmeta_conditions = 'pm.meta_key IN (%s)';
        $where               = "p.post_type = 'post' AND p.post_status = 'publish' AND 
                                p.post_password = '' AND pm.post_id IS NULL";

        switch ( $order_by ) {
            case 'post_title':
                $order_by = 'p.post_title ASC';
                break;
            case 'post_date_asc':
                $order_by = 'p.post_date ASC';
                break;
            default:
                $order_by = 'p.post_date DESC';
                break;
        }
        
        $fields    = 'p.ID, p.post_date, p.post_title, p.post_status, p.post_name, p.post_type';
        $meta_keys = $this->db->prepareMetaKey( 'exclude_from_site_tree' );

        if ( $this->getListOption( 'show_excerpt' ) ) {
        	$extract_length = 3 * $this->getListOption( 'excerpt_length', 100 );
			$fields        .= ", p.post_excerpt, LEFT( p.post_content, {$extract_length} ) AS post_extract";
        }
           
        if ( $this->getListOption( 'show_comments_count' ) ) {
            $fields .= ', p.comment_count';
        }
        
        switch ( $this->getListOption( 'group_by' ) ) {
            case 'date':
                $fields  .= ', MONTH(p.post_date) AS post_month';
                $order_by  = 'p.post_date';
                break;
            case 'category':
                $fields  .= ', t.term_id, t.slug AS category_slug, t.name AS category';
                $joins   .= "INNER JOIN {$this->wpdb->term_relationships} AS tr
                                ON tr.object_id = p.ID
                             CROSS JOIN {$this->wpdb->term_taxonomy} AS tt
                                USING( term_taxonomy_id )
                             CROSS JOIN {$this->wpdb->terms} AS t USING( term_id )";
                $where   .= " AND tt.taxonomy = 'category'";
                $order_by = 'category, ' . $order_by;

                $this->appendToQueryClause( 'group_by', 'p.ID' );
                break;
            case 'author':
                $fields  .= ', p.post_author, u.user_nicename AS author_nicename, 
                             u.display_name AS author_name';
                $joins   .= "INNER JOIN {$this->wpdb->users} AS u 
                                ON p.post_author = u.ID";
                $order_by  = 'u.display_name, ' . $order_by;
                break;
        }

        $this->appendToQueryClause( 'fields', $fields );
        $this->appendToQueryClause( 'from', "{$this->wpdb->posts} AS p" );
        $this->appendToQueryClause( 'where', $where );
        $this->appendToQueryClause( 'order_by', $order_by );

        $this->prepareVariablesOnDoingHyperlist( $meta_keys, $postmeta_conditions );

        $postmeta_conditions = sprintf( $postmeta_conditions, $meta_keys );
        
        $this->appendToQueryClause( 'joins', "LEFT OUTER JOIN {$this->wpdb->postmeta} AS pm
                                              ON p.ID = pm.post_id AND {$postmeta_conditions}" );
        $this->appendToQueryClause( 'joins', $joins );

        return $this->queryDB();
    }

    /**
     * @since 2.0
     * @return bool
     */
    private function popStickyPosts() {
        $stickies_IDs = get_option( 'sticky_posts' );

        if (! is_array( $stickies_IDs ) ) {
            return false;
        }

        $is_sticky_flags = $stickies = array();

        foreach ( $stickies_IDs as $id ) {
            $id = (int) $id;

            if ( $id > 0 ) {
                $is_sticky_flags[$id] = true;
            }
        }

        foreach ( $this->queryResults as $index => $post ) {
            if ( isset( $is_sticky_flags[$post->ID] ) ) {
                $stickies[] = $post;

                unset( $this->queryResults[$index] );
            }
        }

        $this->queryResults = array_merge( $stickies, $this->queryResults );
    }
    
    /**
     * @since 4.4
     *
     * @param object $post
     * @param bool $do_hyperlink
     * @return string
     */
    private function getPostGroupTitle_date( $post, $do_hyperlink ) {
        $year  = mysql2date( 'Y', $post->post_date );
        $month = mysql2date( 'm', $post->post_date );
        $date  = mysql2date( 'F Y', $post->post_date );

        if (! $do_hyperlink ) {
            return $date;  
        }
        
        $permalink  = get_month_link( $year, $month );
        $link_title = sprintf( __( 'View all posts published on %s', 'sitetree' ), $date );

        return '<a href="' . $permalink . '" title="' . $link_title . '">' . $date . '</a>';
    }
    
    /**
     * @since 4.4
     *
     * @param object $post
     * @param bool $do_hyperlink
     * @return string
     */
    private function getPostGroupTitle_category( $post, $do_hyperlink ) {
        $term_name = esc_attr( strip_tags( $post->category ) );

        if (! $do_hyperlink ) {
            return $term_name;
        }

        $term                    = new \stdClass();
        $term->term_id           = $post->term_id;
        $term->term_taxonomy_id  = $post->term_id;
        $term->name              = $term_name;
        $term->slug              = $post->category_slug;
        $term->taxonomy          = 'category';
        
        wp_cache_add( $term->term_id, $term, 'category' );

        $group_title  = '<a href="' . get_term_link( $term ) . '" title="';
        $group_title .= sprintf( __( 'View all posts filed under %s', 'sitetree' ), $term_name );
        $group_title .= '">' . $term_name . '</a>';
        
        return $group_title;
    }
    
    /**
     * @since 4.4
     *
     * @param object $post
     * @param bool $do_hyperlink
     * @return string
     */
    private function getPostGroupTitle_author( $post, $do_hyperlink ) {
        $author_name = esc_attr( strip_tags( $post->author_name ) );

        if (! $do_hyperlink ) {
            return $author_name;
        }

        $group_title  = '<a href="';
        $group_title .= get_author_posts_url( $post->post_author, $post->author_nicename );
        $group_title .= '" title="';
        $group_title .= sprintf( __( 'View all posts by %s', 'sitetree' ), $author_name );
        $group_title .= '">' . $author_name . '</a>';
        
        return $group_title;
    }
}


/**
 * @since 2.0
 */
class SiteTreeWalker extends \Walker {
    /**
     * @since 2.0
     * @var object
     */
    private $builder;

    /**
     * @see parent::$tree_type
     * @since 2.0
     *
     * @var string
     */
    public $tree_type;

    /**
     * @since 2.0
     * @param object $builder
     */
    public function __construct( $builder ) {
        $this->builder   = $builder;
        $this->tree_type = $builder->listID();
    }

    /**
     * @see parent::start_lvl()
     * @since 2.0
     */
    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        $output .= "\n". '<ul class="site-tree-child-list">' . "\n";
    }

    /**
     * @see parent::end_lvl()
     * @since 2.0
     */
    public function end_lvl( &$output, $depth = 0, $args = array() ) {
        $output .= "</ul>\n";
    }

    /**
     * @see parent::end_el()
     * @since 2.0
     */
    public function end_el( &$output, $object, $depth = 0, $args = array() ) {
        $output .= "</li>\n";

        $this->builder->incrementItemsCounter();
    }
}


/**
 * @since 3.0
 */
final class CustomPostWalker extends SiteTreeWalker {
    /**
     * @see Walker::$db_fields
     * @since 3.0
     *
     * @var array
     */
    public $db_fields = array(
        'id'     => 'ID',
        'parent' => 'post_parent'
    );

    /**
     * @see Walker::start_el()
     * @since 3.0
     */
    public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
        $output .= '<li><a href="' . get_permalink( $object ) . '">';
        $output .= apply_filters( 'the_title', $object->post_title, $object->ID );
        $output .= '</a>';
    }
}

/**
 * @since 4.4
 */
final class PageWalker extends SiteTreeWalker {
    /**
     * @see Walker::$db_fields
     * @since 4.4
     *
     * @var array
     */
    public $db_fields = array(
        'id'     => 'ID',
        'parent' => 'post_parent'
    );

    /**
     * @since 4.4
     * @var bool
     */
    private $dehyperlinkParents;

    /**
     * @since 4.4
     * @var int
     */
    private $dehyperlinkingLevel;

    /**
     * @since 4.4
     * @var int
     */
    private $idsGroupedByLevelAndParent = array();

    /**
     * @see parent::__construct()
     * @since 4.4
     */
    public function __construct( $builder ) {
        parent::__construct( $builder );

        $this->dehyperlinkParents = (bool) $builder->getListOption( 'dehyperlink_parents' );

        if ( $this->dehyperlinkParents ) {
            $this->dehyperlinkingLevel = (int) $builder->getListOption( 'dehyperlinking_level', 0 );

            $level     = 0;
            $max_level = $this->dehyperlinkingLevel + 1;
            $pages     = get_pages();

            foreach( $pages as $page ) {
                $level = ( ( $page->post_parent == 0 ) ? 0 : ++$level );

                if ( $level <= $max_level ) {
                    $this->idsGroupedByLevelAndParent[$level][$page->post_parent] = $page->ID;
                }
            }
        }
    }

    /**
     * @see Walker::start_el()
     * @since 4.4
     */
    public function start_el( &$output, $page, $depth = 0, $args = array(), $current_object_id = 0 ) {
        $the_title = apply_filters( 'the_title', $page->post_title, $page->ID );

        if ( $this->dehyperlinkParents && ( $depth <= $this->dehyperlinkingLevel ) ) {
            $page_has_child  = isset( $this->idsGroupedByLevelAndParent[$depth + 1][$page->ID] );
            $dehyperlink     = $page_has_child;

            if ( $dehyperlink ) {
                $h_level = 'h' . ( 5 + $depth );
                $output .= '<li class="site-tree-dehyperlinked-parent">' 
                         . "\n<" . $h_level . '>' . $the_title . '</' . $h_level . '>';
                
                return true;
            }
        }

        $output .= '<li><a href="' . get_permalink( $page ) . '">' . $the_title . '</a>';
    }
}


/**
 * @since 3.0
 */
final class TaxonomyWalker extends SiteTreeWalker {
    /**
     * @see Walker::$db_fields
     * @since 3.0
     *
     * @var array
     */
    public $db_fields = array(
        'id'     => 'term_id',
        'parent' => 'parent'
    );

    /**
     * @see Walker::start_el()
     * @since 3.0
     */
    public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
        if ( $object->description )
            $link_title = $object->description;
        else
            $link_title = sprintf( __( 'View all posts filed under %s', 'sitetree' ), $object->name );
        
        $output .= '<li><a href="' . get_term_link( $object ) . '" title="';
        $output .= esc_attr( strip_tags( $link_title ) );
        $output .= '">' . esc_attr( $object->name ) . '</a>';
        
        if ( $args['feed'] ) {
            $output .= ' (<a href="';
            $output .= get_term_feed_link( $object->term_id );
            $output .= '">' . esc_attr( $args['feed'] ) . '</a>)';
        }
            
        if ( $args['show_count'] )
            $output .= ' <span class="site-tree-posts-count">(' . (int) $object->count . ')</span>';
    }
}
?>