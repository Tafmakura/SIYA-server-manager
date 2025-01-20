<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {



    public function __construct() {

        echo "Hello World";

        add_filter('manage_shop_subscription_posts_columns', array($this, 'add_custom_column'), 20);
        add_action('manage_shop_subscription_posts_custom_column', array($this, 'render_custom_column'), 10, 2);
    }

    /**
     * Add custom column to subscriptions list
     */
    public function add_custom_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            // Add custom column after order title
            if ($key === 'order_title') {
                $new_columns['my_custom_column'] = __('Custom Column', 'your-text-domain');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function render_custom_column($column, $subscription_id) {
        if ('my_custom_column' === $column) {
            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription) {
                // Add your custom column logic here
                echo esc_html('Your custom data');
            }
        }
    }
}

