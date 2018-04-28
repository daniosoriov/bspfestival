jQuery(document).ready(function ($) {

    gform.addFilter('gform_product_total', function (total, formId) {
        //only apply logic to form ID 165
        //if(formId != 1)
        //  return total;
        console.log('HERE inside :) total: ' + total);
        //if(jQuery(".ginput_quantity").val() > 100)
        total += 50;
        console.log('HERE inside :) formId: ' + formId);

        return total;
    });


});