<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class Upgrader extends PluginUpgrader {
    /**
     * @see parent::upgrade()
     * @since 5.0
     */
    public function upgrade( $version_to_upgrade_from ) {
        if ( version_compare( $version_to_upgrade_from, '1.5.3', '<=' ) ) {
            $this->upgradeExclusions( 'xml', 'sitemap' );
            $this->upgradeExclusions( 'html5', 'site_tree' );
            $this->deletePriorityAndChangefreqMetadata();

            $this->db->overwriteOptions( array() );
            
            delete_option( '_sitetree_backup' );
            delete_transient( 'sitetree_html5' );
            delete_transient( 'sitetree_xml' );
        }
        elseif ( version_compare( $version_to_upgrade_from, '6.0', '>=' ) ) {
            if ( version_compare( $version_to_upgrade_from, '6.0.2', '<' ) ) {
                $this->includePageContentTypeInSitemap();
            }
        }
        elseif ( version_compare( $version_to_upgrade_from, '5.0', '>=' ) ) {
            if ( version_compare( $version_to_upgrade_from, '5.1', '<' ) ) {
                $this->renameExcludeChildsOption();
            }
            elseif ( $version_to_upgrade_from == '5.3.2' ) {
                $this->db->deleteOption( 'ask4donation_clicked' );
            }

            $this->includePageContentTypeInSitemap();
            $this->db->deleteNonAutoloadOption( 'stats' );

            $this->plugin->registerRewriteRules();
            flush_rewrite_rules( false );
        }
        elseif ( version_compare( $version_to_upgrade_from, '4.0', '>=' ) ) {
            if ( version_compare( $version_to_upgrade_from, '4.1', '<' ) ) {
                $this->upgradePingState();
            }

            if ( version_compare( $version_to_upgrade_from, '4.3', '<' ) ) {
                $this->upgradePositionOptions();
            }

            if ( version_compare( $version_to_upgrade_from, '4.5.2', '<' ) ) {
                $this->convertLimitOptions();
            }

            $this->upgradeSitemapExcludedTaxonomyIDsOptions();
            $this->renameExcludeChildsOption();
            $this->deletePriorityAndChangefreqMetadata();
            
            $this->db->deleteNonAutoloadOption( 'stats' );

            $this->plugin->registerRewriteRules();
            flush_rewrite_rules( false );
        }
        else {
            if ( version_compare( $version_to_upgrade_from, '3.0', '>=' ) ){
                $this->db->deleteOption( 0, 'site_tree' );
                $this->renameExcludeChildsOption();

                if ( $version_to_upgrade_from == '3.2' ) {
                    $this->upgradePingState();
                }
            }
            else {
                if ( version_compare( $version_to_upgrade_from, '2.0.2', '<' ) ) {
                    $this->db->deleteOption( 'is_first_activation' );
                }
                
                if ( version_compare( $version_to_upgrade_from, '2.1', '<' ) ) {
                    $this->db->deleteOption( 'cache' );
                    $this->db->deleteOption( 'show_credit' );
                    $this->db->deleteOption( 'ask4help_displayed' );
                }

                if ( version_compare( $version_to_upgrade_from, '2.2', '<' ) ) {
                    $this->upgradeExcludedAuthorsList();
                }

                $this->upgradeIncludeOptions();
                $this->db->deleteOption( 'home_changefreq' );
                $this->db->deleteOption( 'page', 'sitemap' );
                $this->db->deleteOption( 'post', 'sitemap' );
                $this->db->deleteOption( 'authors', 'sitemap' );
                $this->db->deleteOption( 'changefreq', 'category', 'sitemap' );
                $this->db->deleteOption( 'changefreq', 'post_tag', 'sitemap' );
                $this->db->deleteOption( 'priority', 'category', 'sitemap' );
                $this->db->deleteOption( 'priority', 'post_tag', 'sitemap' );
            }

            $this->upgradePositionOptions();
            $this->db->deleteOption( 'sitemap_info' );
            $this->db->deleteOption( 'site_tree_info' );
            $this->db->deleteOption( 'items_limit' );

            $options = $this->db->getOptions();
            $options = self::moveArrayElement( $options, array( 'sitemap_active' ), array( 'is_sitemap_active', 'sitemap' ) );
            $options = self::renameArrayKeys( $options, array( 'site_tree', 'tags' ), array( 'site_tree', 'post_tag' ) );
            $options = self::renameArrayKeys( $options, 
                                              array( 'site_tree_content_types', 'tags' ),
                                              array( 'site_tree_content_types', 'post_tag' ) );

            $this->db->overwriteOptions( $options );
            $this->deletePriorityAndChangefreqMetadata();

            delete_transient( 'sitetree_site_tree' );
            delete_transient( 'sitetree_sitemap' );

            $this->plugin->registerRewriteRules();
            flush_rewrite_rules( false );
        }
    }

    /**
     * @since 5.0
     *
     * @param string $old_context
     * @param string $new_context
     * @return bool
     */
    private function upgradeExclusions( $old_context, $new_context ) {
        $ids = $this->db->getOption( $old_context, array(), 'exclude' );

        if (! ( $ids && is_array( $ids ) ) ) {
            return false;
        }

        $list_of_ids = '';

        foreach ( $ids as $id ) {
            $id = (int) $id;

            if ( $id > 0 ) {
                $list_of_ids .= $id . ',';
            }
        }

        // Removes the trailing comma from the string.
        $list_of_ids = substr( $list_of_ids, 0, -1);

        if (! $list_of_ids ) {
            return false;
        }

        $meta_key = '_sitetree_exclude_from_' . $new_context;

        $this->wpdb->query(
            "INSERT INTO {$this->wpdb->postmeta} (post_id, meta_key, meta_value)
                SELECT ID, '{$meta_key}', 1 FROM {$this->wpdb->posts} AS p
                LEFT OUTER JOIN {$this->wpdb->postmeta} AS pm
                             ON p.ID = pm.post_id AND pm.meta_key = '{$meta_key}'
                WHERE ID IN ({$list_of_ids}) AND post_type IN ('post', 'page') AND 
                            post_status = 'publish' AND pm.post_id IS NULL"
        );

        return true;
    }

    /**
     * Converts a comma-separated list of display names into a
     * comma-separated list of nicknames.
     *
     * @since 5.0
     * @return bool
     */
    private function upgradeExcludedAuthorsList() {
        $excluded_authors_list = $this->db->getOption( 'exclude', '', 'authors', 'site_tree' );

        if (! $excluded_authors_list ) {
            return false;
        }

        $excluded_authors = explode( ',', $excluded_authors_list );

        if (! $excluded_authors ) {
            return false;
        }

        $nicknames = $display_names = array();
        $users     = get_users();

        foreach ( $excluded_authors as $display_name ) {
            $display_name = trim( $display_name );

            if ( preg_match( '/[^a-zA-Z\040\.-]/', $display_name ) === 0 ) {
                $display_names[$display_name] = $display_name;
            }
        }

        foreach ( $users as $user ) {
            if ( isset( $display_names[$user->display_name] ) ) {
                $nicknames[] = sanitize_text_field( $user->user_nicename );
            }
        }

        if (! $nicknames ) {
            return false;
        }

        $this->db->setOption( 'exclude', implode( ', ', $nicknames ), 'authors', 'site_tree' );

        return true;
    }

    /**
     * @since 5.0
     */
    private function upgradeIncludeOptions() {
        $content_flags = array(
            'page'     => false,
            'post'     => false,
            'authors'  => false,
            'category' => false,
            'post_tag' => false
        );

        foreach ( array( 'sitemap', 'site_tree' ) as $sitemap_id )  {
            if ( is_array( $this->db->getOption( $sitemap_id ) ) ) {
                $sitemap_content_flags   = $content_flags;
                $content_types_option_id = $sitemap_id . '_content_types';
                $at_least_one_content_type_is_included = false;

                foreach ( $sitemap_content_flags as $content_type => $flag ) {
                    if ( $this->db->getOption( 'include', false, $content_type, $sitemap_id ) ) {
                        $sitemap_content_flags[$content_type]  = true;
                        $at_least_one_content_type_is_included = true;
                    }

                    $this->db->deleteOption( 'include', $content_type, $sitemap_id );
                }

                if (! $at_least_one_content_type_is_included ) {
                    $sitemap_content_flags['page'] = true;
                }

                $this->db->setOption( $content_types_option_id, $sitemap_content_flags );
            }
            
            $content_flags['tags'] = false;

            unset( $content_flags['post_tag'] );
        }
    }

    /**
     * @since 5.0
     */
    private function upgradePingState() {
        $this->plugin->load( 'includes/ping-state.class.php' );

        $pingState = $this->db->getNonAutoloadOption( 'pingState' );

        if ( $pingState instanceof PingState ) {
            $pingState->resetTimes();
            $pingState->setSitemapID( 'sitemap' );
        }
        else {
            $pingState = new PingState( 'sitemap' );
        }

        $this->db->deleteNonAutoloadOption( 'pingState' );
        $this->db->setNonAutoloadOption( 'pingState', $pingState, 'sitemap' );
    }

    /**
     * @since 5.0
     * @return bool
     */
    private function upgradePositionOptions() {
        $site_tree_content_options = $this->db->getOption( 'site_tree' );
        
        if (! is_array( $site_tree_content_options ) ) {
            return false;
        }

        $ordered_content_types = array();
        $keys          = array_keys( $site_tree_content_options );

        foreach ( $keys as $key ) {
            $ordered_content_types[$key] = false;

            unset( $site_tree_content_options[$key]['position'] );
        }

        $site_tree_content_types = $this->db->getOption( 'site_tree_content_types' );

        if (! is_array( $site_tree_content_types ) ) {
            return false;
        }

        foreach ( $site_tree_content_types as $key => $value ) {
            $ordered_content_types[$key] = (bool) $value;
        }

        $this->db->setOption( 'site_tree', $site_tree_content_options );
        $this->db->setOption( 'site_tree_content_types', $ordered_content_types );

        return true;
    }

    /**
     * @since 5.0
     */
    private function convertLimitOptions() {
        $post_types = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            $limit = $this->db->getOption( 'limit', false, $post_type, 'site_tree' );

            if ( $limit && ( $limit < 0 )  ) {
                $this->db->setOption( 'limit', 1000, $post_type, 'site_tree' );
            }
        } 
    }

    /**
     * @since 5.0
     */
    private function upgradeSitemapExcludedTaxonomyIDsOptions() {
        $taxonomies = get_taxonomies( array( 'public' => true ) );

        foreach( $taxonomies as $taxonomy_name ) {
            $list_of_ids = (string) $this->db->getOption( 'exclude', '', $taxonomy_name, 'sitemap' );

            if ( $list_of_ids ) {
                $this->db->setOption( $taxonomy_name, $list_of_ids, 'exclude_from_sitemap' );
                $this->db->deleteOption( 'exclude', $taxonomy_name, 'sitemap' );
            }
        }
    }

    /**
     * @since 5.0
     */
    private function deletePriorityAndChangefreqMetadata() {
        $this->wpdb->query( 
            "DELETE FROM {$this->wpdb->postmeta} 
             WHERE meta_key IN ( '_sitetree_priority', '_sitetree_changefreq' )"
        );
    }

    /**
     * @since 5.1
     */
    private function renameExcludeChildsOption() {
        if ( $this->db->getOption( 'exclude_childs', false, 'page', 'site_tree' ) ) {
            $this->db->setOption( 'exclude_children', true, 'page', 'site_tree' );
        }

        $this->db->deleteOption( 'exclude_childs', 'page', 'site_tree' );
    }

    /**
     * @since 6.0.2
     */
    private function includePageContentTypeInSitemap() {
        if ( $this->plugin->isSitemapActive( 'sitemap' ) ) {
            $this->db->setOption( 'page', true, 'sitemap_content_types' );
        }
    }
}