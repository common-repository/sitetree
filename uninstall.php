<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 * ************************************************************ */


if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	include( 'library/base-plugin.class.php' );
    include( 'includes/core.class.php' );

    global $wpdb;

    Core::launch( __DIR__ );

    $db = Core::invoke()->db();

    if ( $db->getOption( 'deep_uninstal' ) ) {
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$db->escapedDBKeyPrefix()}%'" );
        $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '{$db->escapedMetaKeyPrefix()}%'" );
    }

    delete_option( $db->optionsID() );
}
?>