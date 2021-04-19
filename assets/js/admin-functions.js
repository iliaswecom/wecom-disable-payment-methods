jQuery(document).ready(function ( $ ) {
    // Dropdown search
    $(".dropdown-input").on("keyup", function() {
        var value = $(this).val().toLowerCase().replace( ' ', '' ).replace( '-', '' ).replace( '_', '' );
        $(this).closest('.dropdown-menu').find("li").filter(function() {
          $(this).toggle($(this).text().toLowerCase().replace( ' ', '' ).replace( '-', '' ).replace( '_', '' ).indexOf(value) > -1)
        });
      });
      $(".dropdown-menu li").click(function () {
          $dropdown = $(this).closest('.dropdown');
          $dropdown.find('.dropdown-toggle-label').text( $(this).text() );
          $dropdown.attr('data-id', $(this).attr('data-id'));
          $dropdown.attr('data-name', $(this).attr('data-name'));
      });

    // Table search
    $('.wdpm-table-input').on("keyup", function() {
        var value = $(this).val().toLowerCase().replace( ' ', '' ).replace( '-', '' ).replace( '_', '' );
        $(this).closest('.wdpm-table-section-wrapper').find(".wpdm-item").filter(function() {
          $(this).toggle($(this).find('td.name').text().toLowerCase().replace( ' ', '' ).replace( '-', '' ).replace( '_', '' ).indexOf(value) > -1)
        });
      });


    // Disable method
    $('.wpdm-item .wpdm-disable-item').click(function () {
        $item = $(this).closest('.wpdm-item');
        $table = $(this).closest('.wpdm-category-table');
        id = $item.attr('data-id');
        name = $item.attr('data-name');
        taxonomy = $table.attr('data-taxonomy');
        method_id = $item.find('.wpdm-payment-method-dropdown > :selected').val();
        method_name = $item.find('.wpdm-payment-method-dropdown > :selected').text();
        if (method_id !== 'default') {
            wdpm_disable_payment_method( method_id, method_name, taxonomy, id, name );
        }
    });
    // Disable combined items
    $('.wpdm-combined-item .wpdm-disable-combined-item').on('click', wdpm_disable_combined_item);

    function wdpm_disable_combined_item () {
        $item = $(this).closest('.wpdm-combined-item');
        method_id = $item.find('.wpdm-payment-method-dropdown > :selected').val();
        method_name = $item.find('.wpdm-payment-method-dropdown > :selected').text();
        $brand_dropdown = $item.find('.dropdown[data-taxonomy="brands"]');
        $category_dropdown = $item.find('.dropdown[data-taxonomy="categories"]');
        brand_id = $brand_dropdown.attr('data-id');
        brand_name = $brand_dropdown.attr('data-name');
        cat_id = $category_dropdown.attr('data-id');
        cat_name = $category_dropdown.attr('data-name');

        if (method_id !== 'default' && cat_name != undefined && brand_name != undefined) {
            wdpm_disable_payment_method( method_id, method_name, 'brands-categories', brand_id + '-' + cat_id, brand_name + '-' + cat_name );
        }
        $(this).off('click', wdpm_disable_combined_item);
        $(this).on('click', wdpm_disable_combined_item);
    }

    function wdpm_disable_payment_method ( method_id, method_name, taxonomy, id, name ) {
        if ( $('.wpdm-disabled-table .wpdm-disabled-item[data-id="' + id + '"][data-method="' + method_id + '"]').length ) {
            return;
        }
        disabled_method = '<tr class="wpdm-disabled-item" data-id="' + id + '" data-name="' + name + '" data-taxonomy="' + taxonomy + '" data-method="' + method_id + '" data-methodname="' + method_name + '"><td>' + method_name + '</td><td>' + taxonomy + '</td><td>' + name + '</td><td><button class="wdpm-enable-item">Ενεργοποίηση</button></td></tr>';
        $('.wpdm-disabled-table tbody').append(disabled_method);
        $('.wpdm-disabled-table .wpdm-disabled-item[data-id="' + id + '"][data-method="' + method_id + '"] .wdpm-enable-item').click( function (e) {
            e.preventDefault();
            $(this).closest('.wpdm-disabled-item').remove();
        } );
    }

    // Enable method
    $('.wpdm-disabled-item .wdpm-enable-item').click( function (e) {
            e.preventDefault();
            $(this).closest('.wpdm-disabled-item').remove();
        } );

    // Submit button functions
    $('.wdpm-submit-btn').click( function (e) {
        e.preventDefault();
        $form = $(this).closest('.wpdm-disabled-form');
        $methods = $form.find('.wpdm-disabled-item');
        items_count = $methods.length;
        methods_list = {};
        index = 0
        $methods.each(function () {
            item_method = $(this).attr('data-method');
            method_name = $(this).attr('data-methodname');
            item_taxonomy = $(this).attr('data-taxonomy');
            item_id = $(this).attr('data-id');
            item_name = $(this).attr('data-name');
            methods_list[index] = { 'payment_method': item_method, 'payment_method_name': method_name, 'taxonomy': item_taxonomy, 'id': item_id, 'name': item_name };
            index++;
        });
        methods_list_string = JSON.stringify(methods_list);
        $form.find('.wpdm-methods-list').val(methods_list_string);
        $form.trigger('submit');
    } );

});