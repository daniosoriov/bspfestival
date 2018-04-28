<?php

/**
 * Controls Gravity Forms.
 *
 * https://www.gravityhelp.com/documentation/article/gform_pre_render/
 *
 * http://www.mootpoint.org/blog/gravity-forms-count-uploaded-files-programmatically/
 */


add_filter('gform_register_init_scripts', 'gform_my_function');
function gform_my_function($form) {
  
  /*
  $uti = new BSPFCouponClass();
  $uti->isValidCoupon('test');
  $uti->isValidCoupon('BSPFTPAAD');
  $uti->isValidCoupon('BSPFTPAJF');
  $uti->isValidCoupon('BSPFTPARS');
  */
  
  $script = '
  (function($){
    $("li.gf_readonly input").attr("readonly","readonly");
    
    gwCountFiles = function (fieldID, formID) {
 
        function getAllFiles(formID) {
            var selector = \'#gform_uploaded_files_\' + formID,
                $uploadedFiles = jQuery(selector), files;
            files = $uploadedFiles.val();
            files = \'\' === files ? {} : jQuery.parseJSON(files);
            return files;
        }

        function getFiles(fieldID, formID) {
            var allFiles = getAllFiles(formID);
            var inputName = getInputName(fieldID);

            if (typeof allFiles[inputName] == \'undefined\')
                allFiles[inputName] = [];
            return allFiles[inputName];
        }

        function getInputName(fieldID) {
            return "input_" + fieldID;
        }

        var files = getFiles(fieldID, formID);
        
        return files.length;

    };
    
    gform.addFilter("gform_file_upload_markup", function (html, file, up, strings, imagesUrl) {
      var formId = up.settings.multipart_params.form_id,
      fieldId = up.settings.multipart_params.field_id,
      targetId = 27; // set this to the field_id of your uploaded files count field

      html = \'<strong>\' + file.name + "</strong> <img class=\'gform_delete\' "
        + "src=\'" + imagesUrl + "/delete.png\' "
        + "onclick=\'gformDeleteUploadedFile(" + formId + "," + fieldId + ", this);countThemFiles(" + formId + ", " + fieldId + ", 0, " + targetId + ");\' "
        + "alt=\'" + strings.delete_file + "\' title=\'" + strings.delete_file + "\' />";
        countThemFiles(formId, fieldId, 1, targetId);
        return html;
    });
    
    countThemFiles = function(formId, fieldId, offset, targetId) {
      //console.log("INSIDE countThemFiles");
      var count = gwCountFiles(fieldId, formId);
      var price = 18;
      
      // Singles Contest
      if (formId == 28) {
	      if ((count + offset) <= 5) {
	        price = (count + offset) * 18;
	      }
	      else if ((count + offset) > 5) {
	        price = (count + offset) * 15;
	      }
	      //console.log("price: "+price);
	      
	      jQuery("#input_" + formId + "_" + targetId).val(price).change();
	      jQuery("#input_" + formId + "_31").val(count + offset).change();
	      //jQuery(\'#field_1_31 .ginput_container_text\').hide();
	      //jQuery(\'#field_1_31 .gfield_description\').html(count + offset);
	      
	      /*
	      gform.addFilter( \'gform_product_total\', function(total, formId) {
	        //console.log(\'total: \'+ total);
	        //console.log(\'fieldId: \'+ fieldId);
	        return price;
	      });*/
	  }
      
    }
    
    jQuery(document).bind(\'gform_page_loaded\', function(event, formId, current_page) {
      //console.log(\'Inside NEW gform_page_loaded with current_page: \'+ current_page);
      
      if (formId == 28 && current_page == 4) {
        var coupon = jQuery(\'#input_28_37\').val();
        //var coupons = ["BSPFTPAAD", "BSPFTPAJF", "BSPFTPARS"];
        //var coupons = ["BSPFBaxtonGB", "BSPFBaxtonJS"];
        var coupons = {
          BSPFBaxtonGB:6,
          BSPFBaxtonJS:6,
        };
        
        //console.log("coupons: "+ coupons);
        //console.log("coupon submitted: "+ coupon);
        
        var validCoupon = false;
        if (coupons.hasOwnProperty(coupon)) {
		  validCoupon = true;
		}
        
        var photosSubmitted = jQuery("#input_28_31").val();
        var price = ((photosSubmitted <= 5) ? 18 : 15) * photosSubmitted;
        //console.log("price: "+ price +" and coupon price: "+coupons[coupon]);
        if (validCoupon) {
          //console.log("photos submitted: "+ photosSubmitted);
          /*
          price = 0;
          if (photosSubmitted == 4 || photosSubmitted == 5) {
            price = (photosSubmitted * 18) - (3 * 18);
          }
          else if (photosSubmitted > 5) {
            price = (photosSubmitted * 15) - (3 * 15);
          }*/
          price = price - coupons[coupon];
          //console.log("new price is: "+ price);
        }
        jQuery("#input_28_27").val(price).change();
      }
      
    });
    
  })(jQuery);
  ';

  GFFormDisplay::add_init_script($form['id'], 'gform_my_function', GFFormDisplay::ON_PAGE_RENDER, $script);
  return $form;
}










// 1 - Tie our validation function to the 'gform_validation' hook
add_filter( 'gform_validation_1', 'bspf_validate_file' );

// CHECK THIS PAGE: http://www.mootpoint.org/blog/gravity-forms-count-uploaded-files-programmatically/
// This is a good example on how to count the amount of files being uploaded and update the value of another form field.
// For example to see if the amount of files uploaded matches the amount selected by the user.

function bspf_validate_file($validation_result) {
  // 2 - Get the form object from the validation result
  $form = $validation_result['form'];
  
  // 3 - Get the current page being validated
  $current_page = rgpost( 'gform_source_page_number_' . $form['id'] ) ? rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;
  
  // Limit the number of photos on the upload field.
  if ( $current_page == 3 ) {
    foreach( $form['fields'] as &$field ) {
      if ( $field->id == '19' ) {
        $field_num_photos_value = rgpost( 'input_17' );
        $pos = strpos($field_num_photos_value, ' ');
        $number_of_photos = substr($field_num_photos_value, 0, $pos);
        $field->prso_pluploader_max_files = $number_of_photos;
        $field->maxFiles = $number_of_photos;
        
        // set the form validation to false
        /*$validation_result['is_valid'] = false;

        $field->failed_validation = true;
        $field->validation_message = 'Val num photos: '. $field_num_photos_value .' value  -->'. rgpost( 'input_19' ) . '<-- File Uploads: '. $number_of_photos.' <pre>field 19 '.print_r($field,1) .'</pre>';*/
        break;
      }
    }
  }
  
  //Assign modified $form object back to the validation result
  $validation_result['form'] = $form;
  return $validation_result;
  
  /*
  if ($current_page == 3) {
    if ( strpos( $field->cssClass, 'validate-photo-amount' ) === true ) {
      
    }
  }
  
  /*
  // 4 - Loop through the form fields
  foreach( $form['fields'] as &$field ) {
    // 5 - If the field does not have our designated CSS class, skip it
    // We could change this to check by field ID or any other field property, but I've found that the CSS class is a user friendly way of quickly adding custom validation functionality to most fields. Basing this check on field ID would have the drawback of requiring a code change if you ever needed to remove the field and apply the custom validation to a different field.
    if ( strpos( $field->cssClass, 'validate-vin' ) === false ) {
      continue;
    }
    
    // 6 - Get the field's page number
    $field_page = $field->pageNumber;
    
    // 7 - Check if the field is hidden by GF conditional logic
    $is_hidden = RGFormsModel::is_field_hidden( $form, $field, array() );
    
    // 8 - If the field is not on the current page OR if the field is hidden, skip it
    if ( $field_page != $current_page || $is_hidden ) {
      continue;
    }
    
    // 9 - Get the submitted value from the $_POST
    // The string we're passing to this function would look something like "input_48" if we weren't dynamically populating the field ID using the current field's ID property.
    $field_value = rgpost( "input_{$field['id']}" );

    // 10 - Make a call to your validation function to validate the value
    $is_valid = is_vin( $field_value );
    if ($is_valid) {
      continue;
    }
    
    // 12 - The field field validation, so first we'll need to fail the validation for the entire form
    $validation_result['is_valid'] = false;

    // 13 - Next we'll mark the specific field that failed and add a custom validation message
    $field->failed_validation = true;
    $field->validation_message = 'The VIN number you have entered is not valid.';
  }
  
  // 14 - Assign our modified $form object back to the validation result
  $validation_result['form'] = $form;

  // 15 - Return the validation result
  return $validation_result;
  */
  
}

function bspf_get_num_photos_map($field) {
  $choices = array();
  $i = 1;
  foreach ($field->choices as $choice) {
    $pos = strpos($choice['price'], ',');
    $choices[$choice['value'] .'|'. substr($choice['price'], 0, $pos)] = $i++;
  }
  return $choices;
}

function is_vin($val) {
  return TRUE;
}