<?php

include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/interfaces/class-internal-wp-interface.php');

class InternalWpService implements WPServiceInterface {

    /**
     * Retrieves a WP option value based on an option name.
     *
     * @param string
     * @return mixed
     */
    public function get_wp_option($option) {
        return get_option($option);
    }

}