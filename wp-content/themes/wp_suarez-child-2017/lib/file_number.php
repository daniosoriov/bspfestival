<?php

add_action( 'gform_enqueue_scripts', 'bspf_set_number_of_uploaded_images', 10, 2 );
add_action( 'gform_pre_render', 'bspf_set_number_of_uploaded_images' );
 
// Set the uploaded images count field and make it readonly
function bspf_set_number_of_uploaded_images( $form ) {
    // only run hook for form_id = 1
    if ( $form['id'] != 1 ) {
       return $form;
    }
  
  ?>

  <script type="text/javascript">

    gform.addFilter( 'gform_product_total', function(total, formId) {
        //only apply logic to form ID 1
        if(formId != 1)
            return total;

      alert('total: '+ total);
        //if (jQuery(".ginput_quantity").val() > 100)
            total += 50;

        return total;
    } );
    
  </script>

  <?php
  
  /*
    // make any input field with the class gf_readonly to be read-only 
    ?>
    <script type="text/javascript">
    $( document ).ready(function() {
        $("li.gf_readonly input").attr("readonly","readonly");
    });
    </script>
    <script type="text/javascript">
 
        gwCountFiles = function (fieldID, formID) {
 
            function getAllFiles(formID) {
                var selector = '#gform_uploaded_files_' + formID,
                    $uploadedFiles = jQuery(selector), files;
                files = $uploadedFiles.val();
                files = '' === files ? {} : jQuery.parseJSON(files);
                return files;
            }
 
            function getFiles(fieldID, formID) {
                var allFiles = getAllFiles(formID);
                var inputName = getInputName(fieldID);
 
                if (typeof allFiles[inputName] == 'undefined')
                    allFiles[inputName] = [];
                return allFiles[inputName];
            }
 
            function getInputName(fieldID) {
                return "input_" + fieldID;
            }
 
            var files = getFiles(fieldID, formID);
 
            return files.length;
 
        };
 
        gform.addFilter('gform_file_upload_markup', function (html, file, up, strings, imagesUrl) {
            var formId = up.settings.multipart_params.form_id,
                fieldId = up.settings.multipart_params.field_id,
                targetId = 17; // set this to the field_id of your uploaded files count field
 
            html = '<strong>' + file.name + "</strong> <img class='gform_delete' "
            + "src='" + imagesUrl + "/delete.png' "
            + "onclick='gformDeleteUploadedFile(" + formId + "," + fieldId + ", this);countThemFiles(" + formId + ", " + fieldId + ", 0, " + targetId + ");' "
            + "alt='" + strings.delete_file + "' title='" + strings.delete_file + "' />";
            countThemFiles(formId, fieldId, 1, targetId);
            return html;
        });
 
        function countThemFiles(formId, fieldId, offset, targetId) {
            var count = gwCountFiles(fieldId, formId);
            jQuery('#input_' + formId + '_' + targetId).val(count + offset).change();
        }
 
    </script>
 
    <?php */
    return $form;
}