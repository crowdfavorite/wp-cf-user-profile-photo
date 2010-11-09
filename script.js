jQuery(function($) {
	// If theres an input type=file, change that item's form element's enctype attr
	$("input[type='file']").each(function() {
		var parentForm = $(this).parents('form').get(0);
		$(parentForm).attr('enctype', 'multipart/form-data');
	});
	
	$("#"+cfuppObj.prefix + cfuppObj.deleteLinkId).click(function() {
		var propAction = cfuppObj.prefix + 'cf_action';
		
		$.post(
			cfuppObj.deleteEndpoint,
			{
				cfuppcf_action: 'delete_profile_photo',
				user_id: $("#user_id").val(),
				cfuppuser_photo_nonce: $("input[name='cfuppuser_photo_nonce']").val(),
				_wp_http_referer: $("input[name='_wp_http_referer']").val()
			},
			function(r) {
				if(r) {
					$("#current_photo_row").fadeOut(function(){
						$(this).remove();
					});
				}
				else {
					alert(cfuppObj.deleteErrorMsg);
				}
			},
			'text'
		);
		return false;
	});
});