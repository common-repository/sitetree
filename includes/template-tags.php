<?php
/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 */

/**
 * Returns a Hyper-list for a given Content Type.
 *
 * @since 5.2
 *
 * @param string $type
 * @param array $arguments
 * @return string
 */
function sitetree_get_hyperlist( $type, $arguments = array() ) {
    if ( !( $type && is_string( $type ) ) ) {
        _doing_it_wrong(
            __FUNCTION__,
            sprintf( __( 'The first parameter passed to %s should be a string representing the Content Type.', 'sitetree' ),
                     '<code>sitetree_get_hyperlist()</code>' ),
            '5.2.0'
        );
    }

    $plugin              = \SiteTree\Core::invoke();
    $hyperlistController = $plugin->invokeGlobalObject( 'HyperlistController' );

    return $hyperlistController->getHyperlist( $type, $arguments );
}
?>