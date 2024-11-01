<?php
namespace SiteTree;

/**
 * @package SiteTree
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 5.0
 */
final class PingController {
    /**
     * @since 5.0
     * @var array
     */
    private $targets = array(
        'google' => 'https://www.google.com/ping?sitemap=',
        'bing'   => 'https://www.bing.com/ping?sitemap='
    );

    /**
     * @since 5.0
     * @var object
     */
    private $plugin;

    /**
     * @since 5.0
     * @var object
     */
    private $db;
    
    /**
     * Persistent object. Instance of {@see PingState}.
     *
     * @since 5.0
     * @var object
     */
    private $pingState;

    /**
     * Number of seconds elapsed since the last ping.
     *
     * @since 5.0
     * @var int
     */
    private $timeSinceLastPing;

    /**
     * @since 5.0
     * @var array
     */
    private $minTimeBetweenPings;

    /**
     * @since 5.0
     * @var array
     */
    private $responses = array();
    
    /**
     * @since 5.0
     * @param object $plugin
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->db     = $plugin->db();

        $five_minutes   = 5 * MINUTE_IN_SECONDS;
        $thirty_minutes = 30 * MINUTE_IN_SECONDS;

        $this->minTimeBetweenPings = array(
            'sitemap' => $thirty_minutes,
            'newsmap' => $five_minutes
        );
    }

    /**
     * @since 5.0
     * @param string $sitemap_id
     */
    public function getPingState( $sitemap_id ) {
        if ( !$this->pingState || ( $this->pingState->sitemapID() != $sitemap_id ) ) {
            $this->pingState = $this->db->getNonAutoloadOption( 'pingState', false, $sitemap_id );

            if ( 
                !( $this->pingState instanceof PingState ) ||
                ( $this->pingState->sitemapID() != $sitemap_id ) 
            ) {
                $this->pingState = new PingState( $sitemap_id );
            }
        }

        return $this->pingState;
    }

    /**
     * @since 5.0
     * @return array
     */
    public function getResponses() {
        return $this->responses;
    }
    
    /**
     * @since 5.0
     *
     * @param string $sitemap_id
     * @param object $post
     */
    public function ping( $sitemap_id, $post = null ) {
        $is_automatic_ping = is_object( $post );

        /**
         * @since 5.3
         */
        $can_ping = apply_filters( 'sitetree_ping_controller_can_ping', true, $sitemap_id, $post );

        if ( 
            !$can_ping ||
            !( $is_automatic_ping || $this->canPingOnRequest( $sitemap_id ) ) || 
            $this->plugin->isWebsiteLocal()
        ) {
            return false;
        }

        $pingState = $this->getPingState( $sitemap_id );
        
        if ( $is_automatic_ping ) {
            $post_id     = $post->ID;
            $status_code = 'automatic_ping';
        }
        else {
            $post_id     = 0;
            $status_code = $pingState->getCode();
        }

        switch ( $status_code ) {
            case 'no_google':
                $this->sendPing( 'google' );
                break;

            case 'no_bing':
                $this->sendPing( 'bing' );
                break;

            default:
                $this->sendPing( 'google' );
            
                if ( $sitemap_id == 'sitemap' ) {
                    $this->sendPing( 'bing' );
                }
                break;
        }

        $pingState->update( $post_id, $this->responses );

        $this->db->setNonAutoloadOption( 'pingState', $pingState, $sitemap_id );

        /**
         * @since 5.0
         */
        do_action( 'sitetree_ping_controller_did_send_pings', $this, $sitemap_id, $post_id );

        // Reset.
        $this->responses = array();
    }

    /**
     * @since 5.0
     *
     * @param string $sitemap_id
     * @return bool
     */
    public function canPingOnRequest( $sitemap_id ) {
        $now = time();

        $pingState        = $this->getPingState( $sitemap_id );
        $status_code      = $pingState->getCode();
        $latest_ping_time = $pingState->getLatestTime();

        $this->timeSinceLatestPing = $now - $latest_ping_time;

        return (
            ( $status_code != 'succeeded' ) ||
            ( $this->timeSinceLatestPing > $this->minTimeBetweenPings[$sitemap_id] )
        );
    }

    /**
     * @since 5.0
     * @param string $search_engine_id
     */
    private function sendPing( $search_engine_id ) {
        $sitemap_id = $this->pingState->sitemapID();

        $url  = $this->targets[$search_engine_id];
        $url .= urlencode( $this->plugin->sitemapURL( $sitemap_id ) );

        $wp_response   = wp_remote_get( $url );
        $is_wp_error   = is_wp_error( $wp_response );
        $response_code = wp_remote_retrieve_response_code( $wp_response );
        
        $this->responses[$search_engine_id]['time'] = time();

        if ( $is_wp_error ) {
            $this->responses[$search_engine_id]['status'] = $wp_response->get_error_message();
        }
        else {
            $this->responses[$search_engine_id]['status'] = (string) $response_code;
        }
    }
    
    /**
     * @since 5.0
     *
     * @param string $sitemap_id
     * @return array
     */
    public function getPingInfo( $sitemap_id ) {
        $ping_info = array( 'ping_failed' => false );
        $pingState = $this->getPingState( $sitemap_id );

        switch ( $pingState->getCode() ) {
            case 'succeeded':
                if ( $sitemap_id == 'sitemap' ) {
                    $status_msg_format = __( 'The latest pings were sent on %s.', 'sitetree' );
                }
                else {
                    $status_msg_format = __( 'Google was last pinged on %s.', 'sitetree' );
                }

                $date = '<time>' . \sitetree_fn\gmt_to_local_date( $pingState->getLatestTime() ) . '</time>';

                $ping_info['ping_btn_title'] = __( 'Ping anew', 'sitetree' );
                $ping_info['status_msg']     = sprintf( $status_msg_format, $date );
                break;

            case 'no_google':
                $ping_info['ping_failed']    = true;
                $ping_info['ping_btn_title'] = __( 'Ping it again', 'sitetree' );
                $ping_info['status_msg']     = __( "I couldn't ping Google.", 'sitetree' );
                break;

            case 'no_bing':
                $ping_info['ping_failed']    = true;
                $ping_info['ping_btn_title'] = __( 'Ping them again', 'sitetree' );
                $ping_info['status_msg']     = __( "I couldn't ping Bing and Yahoo!", 'sitetree' );
                break;

            case 'failed':
                $ping_info['ping_failed']    = true;
                $ping_info['ping_btn_title'] = __( 'Resend pings', 'sitetree' );
                $ping_info['status_msg']     = __( 'Bloody hell, all pings have failed.', 'sitetree' );
                break;

            default:
                $ping_info['ping_btn_title'] = __( 'Ping', 'sitetree' );
                $ping_info['status_msg']     = __( 'No ping sent, yet.', 'sitetree' );
                break;
        }
        
        return $ping_info;
    }

    /**
     * @since 5.0
     *
     * @param string $sitemap_id
     * @return string
     */
    public function getTimeToNextPingInWords( $sitemap_id ) {
        $minutes = ceil( ( $this->minTimeBetweenPings[$sitemap_id] - $this->timeSinceLatestPing ) / MINUTE_IN_SECONDS );

        return sprintf( _n( 'about 1 minute', '%d minutes', $minutes, 'sitetree' ), $minutes );
    }
}
?>