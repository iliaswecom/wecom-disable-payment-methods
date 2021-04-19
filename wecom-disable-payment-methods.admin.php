<?php

if ( ! defined('ABSPATH') ) {
    die( 'ABSPATH is not defined! "Script didn\' run on Wordpress."' );
}
if ( !is_admin() ) {
    die('Not enough privileges');
}


?>
<div class="wdpm-admin-panel container mb-5 mt-5">
    <div>
        <h4 class="mb-4">Λίστα Απενεργοποιημένων Τρόπων Πληρωμής.</h4>
    <?php
        /* 
        wp_option: wdpm_disabled_methods =     array(
                                $payment_method => array(
                                        'brands-categories'    => array(
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                        ),
                                        'brands'    => array(
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                        ),
                                        'categories'    => array(
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                        ),
                                        'subcategories'    => array(
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                            array(
                                                'id'                => (int),
                                                'name'              => (str)
                                            ),
                                        )
                                ), ...
            )
        */
        Wecom_Disable_Payment_Methods::get_pm_form();

        $formatted_brands_arr = Wecom_Disable_Payment_Methods::get_all_brands();
        $formatted_categories_arr = Wecom_Disable_Payment_Methods::get_all_categories();
        $formatted_subcategories_arr = Wecom_Disable_Payment_Methods::get_all_subcategories();
        $formatted_all_categories_arr = array_merge( $formatted_categories_arr, $formatted_subcategories_arr );
    ?>
    </div>
    <div>
        <h4 class="mb-3">Συνδυασμός Brand - Κατηγορίας</h4>
        <div class="mb-4"><em>Επιλογή προϊόντων ενός brand που ανήκουν σε κάποια συγκεκριμένη κατηγορία.</em></div>
        <div class="container mb-5">
            <div class="row wpdm-combined-item">
                <div class="col-sm">
                    <?= Wecom_Disable_Payment_Methods::get_pm_dropdown(); ?>
                </div>
                <div class="col-sm">
                    <?php Wecom_Disable_Payment_Methods::get_searchable_dropdown( 'brands', $formatted_brands_arr ); ?>
                </div>
                <div class="col-sm">
                    <?php Wecom_Disable_Payment_Methods::get_searchable_dropdown( 'categories', $formatted_all_categories_arr ); ?>
                </div>
                <div class="col-sm">
                    <button class="wpdm-disable-combined-item">Απενεργοποίηση</button>
                </div>
            </div>
        </div>
    </div>
    <div>
        <h4 class="mb-4">Όλες οι κατηγορίες.</h4>
        <div>
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="brands-tab" data-toggle="tab" href="#brands" role="tab" aria-controls="brands" aria-selected="true">Brands</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="categories-tab" data-toggle="tab" href="#categories" role="tab" aria-controls="categories" aria-selected="false">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="subcategories-tab" data-toggle="tab" href="#subcategories" role="tab" aria-controls="subcategories" aria-selected="false">Subcategories</a>
                </li>
            </ul>
        </div>
        <div>
            <div class="wdpm-admin-tabs tab-content" id="myTabContent">
                <div class="wdpm-admin-tabs__tab tab-pane fade in show active" id="brands" role="tabpanel" aria-labelledby="brands-tab">
                    <?php
                        Wecom_Disable_Payment_Methods::get_category_tables( 'brands', $formatted_brands_arr );
                    ?>
                </div>
                <div class="wdpm-admin-tabs__tab tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                    <?php
                        Wecom_Disable_Payment_Methods::get_category_tables( 'categories', $formatted_categories_arr );
                    ?>
                </div>
                <div class="wdpm-admin-tabs__tab tab-pane fade" id="subcategories" role="tabpanel" aria-labelledby="subcategories-tab">
                    <?php
                        Wecom_Disable_Payment_Methods::get_category_tables( 'subcategories', $formatted_subcategories_arr );
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>