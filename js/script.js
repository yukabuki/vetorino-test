let range = document.getElementById('points_to_discount_range');
let field = document.getElementById('points_to_discount_num');

// sync les input rang et number
range.addEventListener('input', function (e) {
    field.value = e.target.value;
});
field.addEventListener('input', function (e) {
    range.value = e.target.value;
});


jQuery('#loyalty_points_form').submit(function(e) {
    e.preventDefault();

    jQuery(".success_msg").hide();
    jQuery(".error_msg").hide();

    jQuery.ajax({
        type: "POST",
        url: vt_form_object.ajax_url,
        data: {
            action:'loyalty_points_form',
            points_to_discount: jQuery('input[name="points_to_discount"]').val()
        }
    })
    .done( function(data) {
        if (data.state == 'OK') {
            jQuery(".success_msg").show();
            var new_points = parseInt(jQuery('#loyalty_points_content > p strong').text()) - jQuery('input[name="points_to_discount"]').val();
            jQuery('#loyalty_points_content > p strong').text( new_points );
            if (new_points < parseInt(jQuery('#loyalty_points_form').data('min-points'))) {
                jQuery('#loyalty_points_form').hide();
                jQuery('.nopoints_msg').show();
            }
            else {
                jQuery('#loyalty_points_form input').attr({'max' : new_points});
            }
        }
        else {
            jQuery(".error_msg").show();
        }
    })
    .fail( function(jqXHR, textStatus, errorThrown) {
        jQuery(".error_msg").show();
    });

});