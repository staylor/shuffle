(function ($) {
	var images, audio, video, shuffleForm;

	function setHiddenValue(input, items) {
		var toSend = [];
	
		items.find('li').each(function () {
			var elem = $(this), item_id, ord, orig;
			
			item_id = elem.attr('data-id');
			orig = elem.attr('data-orig-order');
			ord = elem.attr('data-order');
			
			if (ord !== orig) {
				toSend.push({
					id    : parseInt(item_id, 10),
					order : parseInt(ord, 10)
				});
			}
		});
		
		input.val(JSON.stringify(toSend));	
	
		return toSend.length;
	}

	function doSubmit() {
		return parseInt(setHiddenValue($('#image-data'), images) +
			setHiddenValue($('#audio-data'), audio) +
			setHiddenValue($('#video-data'), video), 10) > 0;				
	}

	function createCallback(ctx) {
		return function (event, ui) {
			 ctx.find('li').each(function (i) {
			 	$(this).attr('data-order', i);
			 });
		}
	}
	
	function makeSortable(ctx) {	
		if (ctx) {
			ctx.sortable();
			ctx.bind('sortupdate', createCallback(ctx))
			ctx.disableSelection();	
			ctx.trigger('sortupdate');
		}	
	}
	
	function getAffectedIDs() {
		var rows = [], affected = $.trim($('#affected').val());
		
		//console.log('Affected value is', affected);
		
		if ('' === affected) {
			//console.log('Doing array');
			//console.log('List size is', $('#the-list tr').length);
		
			$('#the-list tr').each(function () {
				var row = $(this), check;
				check = row.find('.check-column').find('input');
				
				if (check.is(':checked')) {
					//console.log('Adding value', check.val());
					rows.push(check.val());
				}
			});
			
			affected = rows.join(',');
		}
		//console.log('Return value is', affected);
		
		return affected;
	}
	
	function addShuffleRadio() {
		var input, label;
		
		input = $('<input/>').attr({
			type : 'radio',
			name : 'find-posts-what',
			id   : 'find-posts-attachment',
			value: 'attachment'
		});
		label = $('<label/>').attr({'for': 'find-posts-attachment'}).text(' Attachment ');	
		
		$(this).append(input).append(label);	
		
		findPosts.send = function() {
			var post = {
				ps: $('#find-posts-input').val(),
				action: 'shuffle_add_attachment_type',
				item_id: getAffectedIDs(),
				_ajax_nonce: $('#_ajax_nonce').val()
			};

			var selectedItem;
			$("input[name='find-posts-what']:checked").each(function() { selectedItem = $(this).val() });
			post['post_type'] = selectedItem;

			$.ajax({
				type : 'POST',
				url : ajaxurl,
				data : post,
				success : function(x) { findPosts.show(x); },
				error : function(r) { findPosts.error(r); }
			});
		};
	}
	
	$(document).ready(function () {
		images = $('#shuffle-images');
		makeSortable(images);
		audio = $('#shuffle-audio');
		makeSortable(audio);
		video = $('#shuffle-video');
		makeSortable(video);
		
		shuffleForm = $('#shuffle-form');
		shuffleForm.submit(doSubmit);
		
		$('#find-posts .find-box-search').each(addShuffleRadio);
	});

}(jQuery));

