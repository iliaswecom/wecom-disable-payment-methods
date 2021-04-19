<?php
/**
 * Plugin Name: Disable Payment Methods - Wecommerce
 * Description: Makes an admin panel to disable payment methods by brand & category
 * Version: 1.1.0
 * Author: wecommerce
 * Requires at least: 5.0
 * Author URI: https://wecommerce.gr
 * Text Domain: wecom-disable-payment-methods
 * WC tested up to: 4.1
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'Wecom_Disable_Payment_Methods' ) ) {

define( 'WDPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

    class Wecom_Disable_Payment_Methods {

        // Instance of this class.
        protected static $instance = null;

        public function __construct () {
            if ( ! class_exists( 'WooCommerce' ) ) {
                return;
            }

            // Admin page
            add_action('admin_menu', array( $this, 'setup_menu' ));
            // Add css & js for admin page
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles_scripts' ) );


            // Add settings link to plugins page
            add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array( $this, 'add_settings_link' ) );

            // Register plugin settings fields
            // register_setting( 'wdpm_settings', 'wdpm_email_message', array('sanitize_callback' => array( 'Wecom_Disable_Payment_Methods', 'wdpm_sanitize_code' ) ) );

            add_action( 'admin_post_wecom_disable_payment_methods_save_form', array( $this, 'save_wdpm_form' ), 10, 1 );

            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_selected_payment_methods' ) );
        }

        public function disable_selected_payment_methods ( $available_gateways ) {
            if ( ! is_checkout() ) return $available_gateways;
            // Get saved option
            $disabled_list = get_option( 'wdpm_disabled_methods', true );
            if ( ! is_array( $disabled_list ) || empty( $disabled_list ) ) {
                return $available_gateways;
            }
            $categories_ancestors = array();
            /* $cat_id => array( $cat_id, ancestors_ids... ) */
            $product_data = array();
            /* $product_id => array( 
                'brand_id'      => $manuf_id,
                'categories'    => $categories_ancestors
            ) */

            // Get product data
            foreach ( WC()->cart->get_cart_contents() as $item ) {
                $product_id = $item['product_id'];
                // Manufacturer
                $product_terms = wp_get_post_terms( $product_id, 'pa_manufacturer', array( 'object_ids' ) );
                $manuf_id = isset( $product_terms[0] ) ? $product_terms[0]->term_id : 0;
                
                // Categories
                    // Get product categories
                    // Search $categories_structure if we have data for the category
                    // Get category parents data & make array with product category ids & parents ids
                $product_all_categories_ids = array();
                $categories = $item['data']->get_category_ids();
                foreach ( $categories as $category_id ) {
                    if ( ! isset( $categories_ancestors[ $category_id ] ) ) {
                        $category_with_parents = get_ancestors( $category_id, 'product_cat', 'taxonomy' );
                        array_push( $category_with_parents, $category_id );
                        $categories_ancestors[ $category_id ] = $category_with_parents;
                    }
                    foreach ( $categories_ancestors[ $category_id ] as $cat_id ) {
                        if ( ! in_array( $cat_id, $product_all_categories_ids ) ) {
                            array_push( $product_all_categories_ids, $cat_id );   
                        }
                    }
                }
                $product_data[ $product_id ] = array(
                    'brand_id'          => $manuf_id,
                    'categories'        => $product_all_categories_ids,
                );
                
            }
            // Foreach disabled payment gateway check if we need to disable it & unset it from the array
            foreach ( $disabled_list as $payment_method => $taxonomies ) {
                if ( ! isset( $available_gateways[ $payment_method ] ) ) {
                    continue;
                }
                $disabled = false;
                foreach ( $taxonomies as $taxonomy => $taxonomy_items ) {
                    $disabled = self::is_disabled( $taxonomy, $taxonomy_items, $product_data );
                    if ( $disabled ) {
                       unset( $available_gateways[ $payment_method ] );
                       continue;
                    }
                }
            }

            return $available_gateways;
        }

        private static function is_disabled ( $taxonomy, $disabled_items, $products ) {
            $disabled = false;
            // Check if taxonomy is combined
            if ( strpos( $taxonomy, '-' ) !== false ) {
                foreach ( $disabled_items as $dis_item ) {
                    $ids = explode( '-', $dis_item['id'] );
                    $disabled_brand_id = $ids[0];
                    $disabled_category_id = $ids[1];
                    $brand_found = false;
                    $category_found = false;
                    foreach ( $products as $product ) {
                        if ( $disabled_brand_id == $product['brand_id'] ) {
                            $brand_found = true;
                        }
                        if ( in_array( $disabled_category_id, $product['categories'] ) ) {
                            $category_found = true;
                        }
                        if ( $brand_found && $category_found ) {
                            return true;
                        }
                    }
                }
            } else {
                if ( $taxonomy == 'brands' ) {
                    foreach ( $disabled_items as $dis_item ) {
                        $disabled_brand_id = $dis_item['id'];
                        foreach ( $products as $product ) {
                            if ( $disabled_brand_id == $product['brand_id'] ) {
                                return true;
                            }
                        }
                    }
                } elseif ( $taxonomy == 'categories' || $taxonomy == 'subcategories' ) {
                    foreach ( $disabled_items as $dis_item ) {
                        $disabled_category_id = $dis_item['id'];
                        foreach ( $products as $product ) {
                            if ( in_array( $disabled_category_id, $product['categories'] ) ) {
                                return true;
                            }
                        }
                    }
                } else {
                    error_log( 'Wecom_Disable_Payment_Methods::is_disabled() > Taxonomy not found' );
                }
            }
            return $disabled;
        }

        public function save_wdpm_form () {
            if ( isset( $_POST['wecom_disable_payment_methods_list'] ) && current_user_can( 'administrator' ) ) {
                $wdpm_list = str_replace( '\\', '', $_POST['wecom_disable_payment_methods_list'] );
                $wdpm_list = json_decode( $wdpm_list, true );
                $wdpm_formatted_list = array();
                // Build list structure
                foreach ( $wdpm_list as $method_list ) {
                    $payment_method = $method_list['payment_method'];
                    $payment_taxonomy = $method_list['taxonomy'];
                    if ( ! isset( $wdpm_formatted_list[ $payment_method ] ) ) {
                        $wdpm_formatted_list[ $payment_method ] = array(
                            $payment_taxonomy => array()
                        );
                    } else {
                        if ( ! isset( $wdpm_formatted_list[ $payment_method ][ $payment_taxonomy ] ) ) {
                            $wdpm_formatted_list[ $payment_method ][ $payment_taxonomy ] = array();
                        }
                    }
                }

                // Add list data
                foreach ( $wdpm_list as $method_list ) {
                    $payment_method = $method_list['payment_method'];
                    $payment_taxonomy = $method_list['taxonomy'];
                    $payment_id = $method_list['id'];
                    $payment_name = $method_list['name'];
                    $payment_method_name = $method_list['payment_method_name'];
                    array_push( $wdpm_formatted_list[ $payment_method ][ $payment_taxonomy ], array(
                        'id'    => $payment_id,
                        'name'  => $payment_name,
                        'payment_method_name'   => $payment_method_name,
                    ) );
                }


                // ob_start();
                // var_dump( $wdpm_formatted_list );
                // error_log( ob_get_clean() );

                // Save methods data
                $updated = update_option( 'wdpm_disabled_methods', $wdpm_formatted_list );
                if ( ! $updated ) {
                    error_log( 'Disable payment methods - not updated' );
                }
            } else {
                error_log( 'not working' );
            }
            header('Location: ' . admin_url( 'tools.php?page=wdpm_settings_page' ) );
        }


        public static function get_pm_form () {
            $disabled_list = get_option( 'wdpm_disabled_methods', true );
            $disabled_items = array();
            if ( is_array( $disabled_list ) && ! empty( $disabled_list ) ) {
                foreach ( $disabled_list as $method => $list ) {
                    foreach ( $list as $taxonomy => $items ) {
                        foreach ( $items as $item ) {
                            array_push( $disabled_items, array(
                                'id'                    => $item['id'],
                                'name'                  => $item['name'],
                                'taxonomy'              => $taxonomy,
                                'payment_method'        => $method,
                                'payment_method_name'   => $item['payment_method_name'],
                            ) );
                        }
                    }
                }
            }
            
            ?>
                <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" class="wpdm-disabled-form mb-4">
                    <input type="hidden" name="action" value="wecom_disable_payment_methods_save_form" />
                    <input type="hidden" class="wpdm-methods-list" name="wecom_disable_payment_methods_list" />
                    <table class="wpdm-disabled-table table mb-4">
                        <thead>
                            <th>Τρόπος Πληρωμής</th>
                            <th>Είδος</th>
                            <th>Κατηγορία</th>
                            <th>Ενεργοποίηση</th>
                        </thead>
                        <tbody>
                        <?php
                        if ( ! empty( $disabled_items ) ) {
                            foreach ( $disabled_items as $disabled_item ) {
                                echo '<tr class="wpdm-disabled-item" data-id="' . $disabled_item['id'] . '" data-name="' . $disabled_item['name'] . '" data-taxonomy="' . $disabled_item['taxonomy'] . '" data-method="' . $disabled_item['payment_method'] . '" data-methodname="' . $disabled_item['payment_method_name'] . '">
                                        <td>' . $disabled_item['payment_method_name'] . '</td>
                                        <td>' . $disabled_item['taxonomy'] . '</td>
                                        <td>' . $disabled_item['name'] . '</td>
                                        <td><button class="wdpm-enable-item">Ενεργοποίηση</button></td>
                                     </tr>';
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                    <p><button class="wdpm-submit-btn button">Αποθήκευση</button></p>
                </form>
            <?php
        }

        public static function get_searchable_dropdown ( $taxonomy, $items ) {
            ?>
                <div class="dropdown d-inline-block" data-taxonomy="<?= $taxonomy ?>">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown"><span class="dropdown-toggle-label"><?= ucfirst( $taxonomy ) ?></span>
                    <span class="caret"></span></button>
                    <ul class="dropdown-menu">
                    <input class="form-control dropdown-input" type="text" placeholder="Αναζήτηση..">
                    <?php
                        foreach ( $items as $item ) {
                            echo '<li data-id="' . $item['id'] . '" data-name="' . $item['name'] . '">' . $item['name'] . '</li>';
                        }
                    ?>
                    </ul>
                </div>
            <?php
        }

        public static function get_category_tables ( $taxonomy, $items ) {
            $payment_methods_dropdown_html = self::get_pm_dropdown();
            ?>
                <div class="wdpm-table-section-wrapper mb-4">
                    <h3 class="mb-4 mt-4"><?= ucwords( $taxonomy ); ?></h3>
                    <div class="mb-4"><input class="form-control wdpm-table-input" type="text" placeholder="Αναζήτηση.."></div>
                    <table class="wpdm-category-table table mb-4"  data-taxonomy="<?= $taxonomy ?>">
                        <thead>
                            <th>Τρόπος Πληρωμής</th>
                            <th>Κατηγορία</th>
                            <th>Απενεργοποίηση</th>
                        </thead>
                        <?php
                            $count = 1;
                            foreach ( $items as $item ) {
                                echo '<tr class="wpdm-item" data-id="' . $item['id'] . '" data-name="' . $item['name'] . '">
                                        <td>' . $payment_methods_dropdown_html . '</td>
                                        <td class="name">' . $item['name'] . '</td>
                                        <td><button class="wpdm-disable-item">Απενεργοποίηση</button></td>
                                     </tr>';
                                $count++;
                            }
                        ?>
                    </table>
                </div>
            <?php
        }

        public static function get_pm_dropdown () {
            $gateways = WC()->payment_gateways->payment_gateways();
            $ret_html = '';
            if ( ! empty( $gateways ) ) {
                $ret_html .= '<select name="wpdm_payment_method" class="wpdm-payment-method-dropdown">';
                $ret_html .= '<option value="default" selected disabled>Επιλογή τρόπου πληρωμής</option>';
                foreach ( $gateways as $gateway ) {
                    $ret_html .= '<option value="' . $gateway->id . '">' . $gateway->title . '</option>';
                }
                $ret_html .= '</select>';
            }

            return $ret_html;
        }

        public static function get_all_brands () {
            $all_brands = get_terms( 'pa_manufacturer', array(
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC',
            ) );
            $formatted_brands_arr = array();
            foreach ( $all_brands as $brand ) {
                array_push( $formatted_brands_arr, array(
                    'name'              => $brand->name,
                    'id'                => $brand->term_id,
                ) );
            }
            return $formatted_brands_arr;
        }

        public static function get_all_categories () {
            $all_categories = get_terms( 'product_cat', array(
                'hide_empty' => false,
                'orderby' => 'name',
                'parent' => 0,
                'order' => 'ASC',
            ) );
            $formatted_categories_arr = array();
            foreach ( $all_categories as $category ) {
                // Proper product count with subcategories
                $query = new WP_Query( array(
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'id',
                            'terms' => $category->term_id, // Replace with the parent category ID
                            'include_children' => true,
                        ),
                    ),
                    'nopaging' => true,
                    'fields' => 'ids',
                ) );
                $product_count = $query->post_count;
                array_push( $formatted_categories_arr, array(
                    'name'              => $category->name,
                    'id'                => $category->term_id,
                ) );
            }
            return $formatted_categories_arr;
        }

        public static function get_all_subcategories () {
            // Get parent categories
            // For every parent > get child categories & add to formatted with ({parent-name})
            $all_categories = get_terms( 'product_cat', array(
                'hide_empty' => false,
                'parent' => 0,
                'orderby' => 'name',
                'order' => 'ASC',
            ) );
            $formatted_subcategories_arr = array();
            foreach ( $all_categories as $parent_category ) {
                $parent_id = $parent_category->term_id;

                $subcategories = get_term_children( $parent_category->term_id, 'product_cat' );
                if ( empty( $subcategories ) ) {
                    continue;
                }
                $middle_category_names = array();
                foreach ( $subcategories as $child_category_id ) {
                    $parent_name = $parent_category->name;
                    
                    $child_category = get_term( $child_category_id, 'product_cat', 'OBJECT' );
                    if ( $child_category->count == 0 ) {
                        $middle_category_names[ $child_category->term_id ] = $child_category->name;
                        continue;
                    }
                    if ( isset( $middle_category_names[ $child_category->parent ] ) ) {
                        $parent_name .= ' > ' . $middle_category_names[ $child_category->parent ];
                    }
                    array_push( $formatted_subcategories_arr, array(
                        'name'              => $parent_name . ' > ' .$child_category->name,
                        'id'                => $child_category->term_id,
                    ) );
                }
            }
            return $formatted_subcategories_arr;
        }

        public static function wdpm_sanitize_code ( $input ) {        
            $sanitized = wp_kses_post( $input );
            if ( isset( $sanitized ) ) {
                return $sanitized;
            } else {
                return '';
            }
        }


        public function setup_menu () {
            add_management_page(
                __( 'Απενεργοποίηση Τρόπων Πληρωμής', 'wecom-disable-payment-methods' ),
                __( 'Απενεργοποίηση Τρόπων Πληρωμής', 'wecom-disable-payment-methods' ),
                'administrator',
                'wdpm_settings_page',
                array( $this, 'admin_panel_page' )
            );
        }

        public function admin_panel_page (){
            require_once( __DIR__ . '/wecom-disable-payment-methods.admin.php' );
        }

        public function admin_styles_scripts () {
            if ( isset( $_GET['page'] ) && $_GET['page'] == 'wdpm_settings_page' ) {
                wp_enqueue_style( 'wecom_disable_payment_methods_admin_css', WDPM_PLUGIN_URL . 'assets/css/admin-styles.css', array(), NULL );
                wp_enqueue_script( 'wecom_disable_payment_methods_admin_js', WDPM_PLUGIN_URL . 'assets/js/admin-functions.js', array('jquery'), NULL, true );

                wp_enqueue_style( 'wecom_disable_payment_methods_bootstrap_css', WDPM_PLUGIN_URL . 'assets/css/bootstrap.min.css', array(), NULL, false );
                wp_enqueue_script( 'wecom_disable_payment_methods_popper_js', WDPM_PLUGIN_URL . 'assets/js/popper.min.js', array('jquery'), time(), true );
                wp_enqueue_script( 'wecom_disable_payment_methods_bootstrap_js', WDPM_PLUGIN_URL . 'assets/js/bootstrap.min.js', array('jquery'), time(), true );
            }
        }

        public function add_settings_link ( $links ) {
            $links[] = '<a href="' . admin_url( 'tools.php?page=wdpm_settings_page' ) . '">' . __('Settings') . '</a>';
            return $links;
        }

        // Return an instance of this class.
		public static function get_instance () {
			// If the single instance hasn't been set, set it now.
			if ( self::$instance == null ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

    }

    add_action( 'plugins_loaded', array( 'Wecom_Disable_Payment_Methods', 'get_instance' ), 0 );

}