jQuery(function($){
	
	var AutocompleteSettings = {
		minChars: 1, 
		multiple: false, 
		scrollHeight: 300, 
		selectFirst: true
	};
	
	var AutocompleteResult = function(e, data) {
		$('form.MoveCommentsForm #Form_DiscussionID').val(data[1]);
	}
	
	var InitializeAutocomplete = function() {
		$('form.MoveCommentsForm').livequery(function(){
			var form = this;
			$('#Form_Name', form)
				.autocomplete(gdn.url('/vanilla/moderation/autocompletediscussionname'), AutocompleteSettings)
				.result(AutocompleteResult);

			$('li.CheckedDiscussionID input', form).change(function(){
				$('#Form_DiscussionID', form).val( $(this).val() );
			});
			
		});

	}
	
	if (typeof($.fn.autocomplete) != 'function') {
		var ScriptURL = gdn.combinePaths(gdn.definition('WebRoot'), '/js/library/jquery.autocomplete.pack.js');
		$.getScript(ScriptURL, InitializeAutocomplete);
	} else InitializeAutocomplete();
	
});
