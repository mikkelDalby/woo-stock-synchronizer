<?php
/**
 * Plugin Name: Stock syncronizer
 * Version: 0.0.2
 * Author: Mikkel Dalby
 * Author URI: https://mikkeldalby.dk
 * Description: This plugin helps with syncronizing product stock across multiple products in your shop.
 */

 /**
* Check if WooCommerce is active
**/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // Put your plugin code here

    // Define new columns
    function stock_sync_set_columns($columns) {
        $columns['product_to_sync'] = __('Stock sync', 'cs-text');
    
        return $columns;
    }
    add_filter( 'manage_product_posts_columns', 'stock_sync_set_columns');
    
    // Show custom field in a new column
    function stock_sync_custom_column( $column, $post_id ) {
    
        switch ( $column ) {
            case 'product_to_sync' : // This has to match to the defined column in function above
                $product_ids = get_post_meta( $post_id, '_stock_sync_data_ids', true );
                $product2 = null;
                
                if (isset($product_ids[0])) {

                    $product = wc_get_product( $product_ids[0] );
                    echo '(' . strval($product_ids[0]) . ') ' . wp_kses_post( $product->get_formatted_name());
                }
                break;
        }
        
    }
    add_action( 'manage_product_posts_custom_column' , 'stock_sync_custom_column', 10, 2 );

    add_filter('woocommerce_product_data_tabs', function($tabs) {
    	$tabs['additional_info'] = [
    		'label' => __('Stock sync', 'txtdomain'),
    		'target' => 'stock_sync_data',
    		'priority'  => 80,
            'class'     => array()
    	];
    	return $tabs;
    });

    add_action('woocommerce_product_data_panels', function() {
        global $post, $woocommerce;

        $product_ids = get_post_meta( $post->ID, '_stock_sync_data_ids', true );
        if( empty($product_ids) )
            $product_ids = array();
        ?>
        <div id="stock_sync_data" class="panel woocommerce_options_panel hidden">
            <?php if ( $woocommerce->version >= '3.0' ) : ?>
                <p class="form-field stock_sync_data">
                    <label for="stock_sync_data"><?php _e( 'Select product to sync with. (Only select 1 item)', 'woocommerce' ); ?></label>
                    <select class="wc-product-search" style="width: 50%;" multiple="multiple" id="stock_sync_data" name="stock_sync_data[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-maximum-selection-length="1" data-exclude="<?php echo intval( $post->ID ); ?>">
                        <?php
                            foreach ( $product_ids as $product_id ) {
                                $product = wc_get_product( $product_id );
                                if ( is_object( $product )) {
                                    echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                                }
                            }
                        ?>
                    </select> <?php echo wc_help_tip( __( 'Select product to syncronize stock.', 'woocommerce' ) ); ?>

                </p>
            <?php endif; ?>
        </div>
        <?php
    });

    add_action('woocommerce_process_product_meta', function($post_id) {
        global $post, $woocommerce;
        // Product Field Type
        $product2 = get_post_meta( $post_id, '_stock_sync_data_ids', true );;
        update_post_meta( $product2[0], '_stock_sync_data_ids', null );

	    $product_field_type = $_POST['stock_sync_data'];
	    update_post_meta( $post_id, '_stock_sync_data_ids', $product_field_type );
	    $other_product = $product_field_type;
	    $other_product[0] = $post_id;
	    update_post_meta( $product_field_type[0], '_stock_sync_data_ids', $other_product );
    });

    add_action( 'woocommerce_update_product', function( $product_id ) {
        global $post, $woocommerce;
        $product = wc_get_product( $product_id );

        $product_ids = get_post_meta( $product_id, '_stock_sync_data_ids', true );
        $product2 = null;
        
        if (isset($product_ids[0])) {
          $product2 = wc_get_product($product_ids[0]);
        } else {
          $error_message = 'Stock-sync: [Product ID: ' . strval($product_id) . ' could not be found]';
          error_log($error_message);
        }
        if ($product2 != null) {
            if ($product->get_stock_quantity() != $product2->get_stock_quantity()) {
                $product2->set_stock_quantity($product->get_stock_quantity());
                $product2->save();
            }
        }

    });
}
