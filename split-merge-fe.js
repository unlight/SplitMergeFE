jQuery(function($){
	
	var AutocompleteSettings = {
		minChars: 1, 
		multiple: false, 
		scrollHeight: 300, 
		selectFirst: true
	};
	
	var AutocompleteResult = function(e, data) {
		$('form.MoveComemntsForm #Form_DiscussionID').val(data[1]);
	}
	
	var InitializeAutocomplete = function() {
		$('form.MoveComemntsForm').livequery(function(){
			var self = this;
			$('#Form_Name', self)
				.autocomplete(gdn.url('/vanilla/moderation/autocompletediscussionname'), AutocompleteSettings)
				.result(AutocompleteResult);

			$('li.CheckedDiscussionID input', self).change(function(){
				$('#Form_DiscussionID', self).val( $(this).val() );
			});
			
		});

	}
	
	if (typeof($.fn.autocomplete) != 'function') {
		var ScriptURL = gdn.combinePaths(gdn.definition('WebRoot'), '/js/library/jquery.autocomplete.pack.js');
		$.getScript(ScriptURL, InitializeAutocomplete);
	} else InitializeAutocomplete();
	
});
