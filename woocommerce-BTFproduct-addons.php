<?php
/*
Plugin Name: WooCommerce BTF Product Addons
Plugin URI: 
Description: A plugin to add products as addons to other products in WooCommerce.
Version: 1.0
Author: Below The Fold
Author URI: https://belowthefold.gr/
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
WC requires at least: 3.0
WC tested up to: 5.9
Text Domain: woocommerce-product-addons
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function wc_product_addons_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( 'This plugin requires WooCommerce to be active.', 'woocommerce-product-addons' ) );
	}
}
register_activation_hook( __FILE__, 'wc_product_addons_activate' );


function wc_product_addons_meta_box() {
	add_meta_box(
		'wc_product_addons',
		__( 'Product Addons', 'woocommerce-product-addons' ),
		'wc_product_addons_meta_box_content',
		'product',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'wc_product_addons_meta_box' );

function wc_product_addons_save( $post_id ) {
	if ( ! isset( $_POST['wc_product_addons_nonce'] ) || ! wp_verify_nonce( $_POST['wc_product_addons_nonce'], 'wc_product_addons_save' ) ) {
		return;
	}

	if ( isset( $_POST['wc_product_addons'] ) && ! empty( $_POST['wc_product_addons'] ) ) {
		$product_addons = array_map( 'intval', $_POST['wc_product_addons'] );
		update_post_meta( $post_id, '_wc_product_addons', $product_addons );
	} else {
		delete_post_meta( $post_id, '_wc_product_addons' );
	}
	
	// Save the discount percentage value.
	$discount_percentage = isset( $_POST['wc_product_addons_discount_percentage'] ) ? intval( $_POST['wc_product_addons_discount_percentage'] ) : 0;
	update_post_meta( $post_id, '_wc_product_addons_discount_percentage', $discount_percentage );
}
add_action( 'save_post', 'wc_product_addons_save' );


function wc_product_addons_display() {
    global $product;

    $product_id = $product->get_id();
    $product_addons = get_post_meta( $product_id, '_wc_product_addons', true );
    $discount_percentage = get_post_meta( $product_id, '_wc_product_addons_discount_percentage', true );

    if ( empty( $product_addons ) ) {
        return;
    }

    // Output the product addons select only once.
    static $output = false;
    if ( ! $output ) {
        $output = true;

        // Wrap the select element in a new div with the class 'wc-product-addons-form'.
        ob_start();
        ?>
        <div class="wc-product-addons-form">
            <h6><strong><?php _e( 'Επιλέξτε πλέγμα', 'woocommerce-product-addons' ); ?></strong></h6>
            <select name="wc_product_addons" class="wc-product-addons-select">
                <option value=""><?php _e( 'Άπλεκτη', 'woocommerce-product-addons' ); ?></option>
                <?php
                foreach ( $product_addons as $addon_id ) {
                    $addon = wc_get_product( $addon_id );
                     if ( $addon ) {
        echo '<option value="' . esc_attr( $addon->get_id() ) . '" data-price="' . esc_attr( $addon->get_price() ) . '">' . esc_html( $addon->get_title() ) . ' - ' . wc_price( $addon->get_price() ) . '</option>';
    }
                }
                ?>
            </select>
            <!-- Add the hidden input field to store the discount percentage -->
            <input type="hidden" name="wc_product_addons_discount_percentage" value="<?php echo esc_attr( $discount_percentage ); ?>">
            <!-- Add the new element to display the message -->
    <p class="wc-product-addons-message" style="display: none;"></p>
     <style>
        .wc-product-addons-message {
            font-weight: bold;
            color: red;
            font-size:12px;
        }
    </style>
    <?php

    // Add a jQuery script to handle the change event on the dropdown
    ?>
    <script>
        (function ($) {
            $(document).ready(function () {
                $('.wc-product-addons-select').on('change', function () {
                    var selectedOption = $(this).find('option:selected');
                    var originalPrice = parseFloat(selectedOption.data('price')); // Assuming 'data-price' attribute stores the original price
                    var discountPercentage = parseFloat($('input[name="wc_product_addons_discount_percentage"]').val());
                    var messageElement = $('.wc-product-addons-message');

                    if (selectedOption.val() !== '') {
                        var discountAmount = originalPrice * (discountPercentage / 100);
                        var message = 'Με την προσθήκη στο καλάθι κερδίζετε €' + discountAmount.toFixed(2);
                        messageElement.text(message).show();
                    } else {
                        messageElement.hide();
                    }
                });
            });
        })(jQuery);
    </script>
        </div>
        <?php
        echo ob_get_clean();
    }
}

add_action( 'woocommerce_before_add_to_cart_button', 'wc_product_addons_display', 5 );
add_action( 'woocommerce_after_add_to_cart_button', 'wc_product_addons_display', 5 );


function wc_product_addons_add_to_cart( $cart_item_data, $product_id ) {
    if ( isset( $_POST['wc_product_addons'] ) && ! empty( $_POST['wc_product_addons'] ) ) {
        $addon_product_id = intval( $_POST['wc_product_addons'] );
        $addon = wc_get_product( $addon_product_id );

        if ( $addon ) {
            $cart_item_data['wc_product_addon_id'] = $addon_product_id;
            $cart_item_data['wc_product_addon_price'] = $addon->get_price();
        }
    }

    return $cart_item_data;
}



add_filter( 'woocommerce_add_cart_item_data', 'wc_product_addons_add_to_cart', 10, 2 );

add_action( 'woocommerce_before_calculate_totals', 'wc_product_addons_apply_discount', 10, 1 );

function wc_product_addons_cart_item_price( $price, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['wc_product_addon_id'] ) && isset( $cart_item['wc_product_addon_price'] ) && isset( $cart_item['wc_product_addon_discount_percent'] ) ) {
        $addon_product = wc_get_product( $cart_item['wc_product_addon_id'] );
        if ( $addon_product ) {
            if ( $cart_item['data']->get_id() == $addon_product->get_id() ) {
               $discounted_price = $cart_item['wc_product_addon_price'] * (1 - $cart_item['wc_product_addon_discount_percent'] / 100);

                $price = '<del>' . wc_price( $addon_product->get_price() ) . '</del> <ins>' . wc_price( $discounted_price ) . '</ins>';
            }
        }
    }

    return $price;
}


add_filter( 'woocommerce_cart_item_price', 'wc_product_addons_cart_item_price', 10, 3 );


function wc_product_addons_apply_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['wc_product_addon_id'] ) ) {
            $addon = wc_get_product( $cart_item['wc_product_addon_id'] );

            if ( $addon && $cart_item['data']->get_id() == $addon->get_id() ) {
                $discount_percentage = isset( $cart_item['wc_product_addon_discount_percent'] ) ? intval( $cart_item['wc_product_addon_discount_percent'] ) : 0;
                $discounted_price = $addon->get_price() * ( 1 - ( $discount_percentage / 100 ) );

                $cart_item['data']->set_price( $discounted_price );
            }
        }
    }
}


add_action( 'woocommerce_before_calculate_totals', 'wc_product_addons_apply_discount', 10, 1 );

function wc_product_addons_cart_item_removed( $cart_item_key, $cart ) {
    $removed_cart_item = $cart->removed_cart_contents[ $cart_item_key ];

    if ( isset( $removed_cart_item['wc_product_addon_id'] ) ) {
        // Get the parent product ID of the removed addon.
        $parent_product_id = intval( $removed_cart_item['product_id'] );

        // Check if the removed addon belongs to any of the cart items.
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['wc_product_addon_id'] ) && $cart_item['wc_product_addon_id'] == $removed_cart_item['wc_product_addon_id'] ) {
                // If the removed addon belongs to a cart item, set the selling price of that item.
                $selling_price = $cart_item['data']->get_sale_price() ? $cart_item['data']->get_sale_price() : $cart_item['data']->get_regular_price();
                $cart_item['data']->set_price( $selling_price );
                $cart->set_session();
            }
        }
    }
}



add_action( 'woocommerce_cart_item_removed', 'wc_product_addons_cart_item_removed', 10, 2 );


function wc_product_addons_get_item_data( $item_data, $cart_item ) {
    if ( isset( $cart_item['wc_product_addon_id'] ) && isset( $cart_item['wc_product_addon_price'] ) && isset( $cart_item['wc_product_addon_discount_percent'] ) ) {
        $addon = wc_get_product( $cart_item['wc_product_addon_id'] );
        if ( $addon ) {
            $item_data[] = array(
                'key'   => __( 'Add-on Product', 'woocommerce-product-addons' ),
                'value' => $addon->get_title(),
            );
            $item_data[] = array(
                'key'   => __( 'Add-on Product Price', 'woocommerce-product-addons' ),
                'value' => wc_price( $cart_item['wc_product_addon_price'] ),
            );
            $item_data[] = array(
                'key'   => __( 'Add-on Product Discount', 'woocommerce-product-addons' ),
                'value' => $cart_item['wc_product_addon_discount_percent'] . '%',
            );
            $item_data[] = array(
                'key'   => __( 'Add-on Product Discounted Price', 'woocommerce-product-addons' ),
                'value' => wc_price( $addon->get_price() * ( 1 - ( $cart_item['wc_product_addon_discount_percent'] / 100 ) ) ),
            );
        }
    }

    return $item_data;
}



function wc_product_addons_add_separate_product_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
    if ( isset( $_POST['wc_product_addons'] ) && ! empty( $_POST['wc_product_addons'] ) ) {
        $addon_product_id = intval( $_POST['wc_product_addons'] );
        $addon = wc_get_product( $addon_product_id );

        if ( $addon ) {
            $parent_product_id = intval( $product_id );
            $discount_percentage = get_post_meta( $parent_product_id, '_wc_product_addons_discount_percentage', true );

            $cart_item_data = array(
                'wc_product_addon_id' => $addon_product_id,
                'wc_product_addon_price' => $addon->get_price(),
                'wc_product_addon_discount_percent' => $discount_percentage,
            );
            WC()->cart->add_to_cart( $addon_product_id, 1, 0, array(), $cart_item_data );
        }
    }

    return $passed;
}



add_filter( 'woocommerce_add_to_cart_validation', 'wc_product_addons_add_separate_product_to_cart', 10, 5 );



function wc_product_addons_meta_box_content( $post ) {
    // Add nonce field for security.
    wp_nonce_field( 'wc_product_addons_save', 'wc_product_addons_nonce' );

    // Get existing product addons.
    $product_addons = get_post_meta( $post->ID, '_wc_product_addons', true );

    // Get existing discount percentage.
    $discount_percentage = get_post_meta( $post->ID, '_wc_product_addons_discount_percentage', true );

    // Display product addons select field.
    ?>
    <select multiple="multiple" name="wc_product_addons[]" class="wc-product-search" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce-product-addons' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
        <?php
        $products = array_filter( array_map( 'wc_get_product', (array) $product_addons ) );
        foreach ( $products as $product ) {
            if ( is_object( $product ) ) {
                echo '<option value="' . esc_attr( $product->get_id() ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
            }
        }
        ?>
    </select>

    <br/><br/>

    <label for="wc_product_addons_discount_percentage"><?php _e( 'Discount Percentage:', 'woocommerce-product-addons' ); ?></label>
    <input type="number" min="0" max="100" step="1" name="wc_product_addons_discount_percentage" id="wc_product_addons_discount_percentage" value="<?php echo esc_attr( $discount_percentage ); ?>" />

    <?php
}


add_action( 'wp_ajax_woocommerce_add_to_cart', 'wc_product_addons_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_woocommerce_add_to_cart', 'wc_product_addons_ajax_add_to_cart' );

function wc_product_addons_ajax_add_to_cart() {
    if ( isset( $_POST['product_id'], $_POST['quantity'], $_POST['addon_id'] ) ) {
        $product_id = absint( $_POST['product_id'] );
        $quantity = absint( $_POST['quantity'] );
        $addon_id = absint( $_POST['addon_id'] );

        $product = wc_get_product( $product_id );
        $addon = wc_get_product( $addon_id );

        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

        if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity ) && WC()->cart->add_to_cart( $addon_id, 1 ) ) {
            // Both products added to cart successfully.
            do_action( 'woocommerce_ajax_added_to_cart', $product_id );
            do_action( 'woocommerce_ajax_added_to_cart', $addon_id );

            if ( get_option( 'woocommerce_cart_redirect_after_add' ) == 'yes' ) {
                wc_add_to_cart_message( array( $product_id => $quantity, $addon_id => 1 ), true );
            }

            // Return fragments.
            WC_AJAX::get_refreshed_fragments();
        } else {
            // Return error.
            wp_send_json( array( 'success' => false, 'error' => __( 'Failed to add the products to the cart.', 'woocommerce-product-addons' ) ) );
        }
    } else {
        // Return error.
        wp_send_json( array( 'success' => false, 'error' => __( 'Invalid request.', 'woocommerce-product-addons' ) ) );
    }

    wp_die();
}