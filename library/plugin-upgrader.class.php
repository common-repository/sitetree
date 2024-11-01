<?php
namespace SiteTree;

/**
 * @version 1.0
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 */
abstract class PluginUpgrader {
    /**
     * @since 1.0
     * @var object
     */
    protected $plugin;

    /**
     * @since 1.0
     * @var object
     */
    protected $db;

    /**
     * @since 1.0
     * @var object
     */
    protected $wpdb;

    /**
     * @since 1.0
     *
     * @param array $array
     * @param array $old_keys
     * @param array $new_keys
     * @return array
     */
    protected static function &renameArrayKeys( &$array, $old_keys, $new_keys ) {
        $new_array = array();
        $old_key   = array_shift( $old_keys );
        $new_key   = array_shift( $new_keys );

        foreach ( $array as $key => &$value ) {
            if ( $key === $old_key ) {
                if ( $old_keys && $new_keys && isset( $value[$old_keys[0]] ) ) {
                    $new_array[$new_key] = self::renameArrayKeys( $value, $old_keys, $new_keys ); 
                }
                else {
                    $new_array[$new_key] = $value;
                }
            }
            else {
                $new_array[$key] = $value;
            }
        }

        return $new_array;
    }

    /**
     * @since 1.0
     *
     * @param array $array
     * @param array $old_keys
     * @param array $new_keys
     * @return array
     */
    protected static function &moveArrayElement( &$array, $old_keys, $new_keys ) {
        $element = null;
        $pointer = &$array;
        
        foreach ( $old_keys as $key ) {
            if (! isset( $pointer[$key] ) ) {
                break;
            }

            $element = $pointer[$key];
            $pointer = &$pointer[$key];
        }

        if ( $element !== null ) {
            unset( $array[$old_keys[0]] );

            $pointer = &$array;

            foreach ( $new_keys as $key ) {
                if (! isset( $pointer[$key] ) ) {
                    $pointer[$key] = array();
                }

                $pointer = &$pointer[$key];
            }

            $pointer = $element;
        }

        return $array;
    }

    /**
     * @since 1.0
     * @param object $plugin
     */
    public function __construct( $plugin ) {
        global $wpdb;

        $this->plugin = $plugin;
        $this->db     = $plugin->db();
        $this->wpdb   = $wpdb;
    }

    /**
     * @since 1.0
     * @param string $version_to_upgrade_from
     */
    abstract public function upgrade( $version_to_upgrade_from );
}