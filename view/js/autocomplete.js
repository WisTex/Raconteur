/**
 * general autocomplete support
 *
 * require jQuery, jquery.textcomplete
 */
function contact_search(term, callback, backend_url, type, extra_channels, spinelement) {
	if(spinelement) {
		$(spinelement).show();
	}

	var postdata = {
		start:0,
		count:100,
		search:term,
		type:type,
	};

	if(typeof extra_channels !== 'undefined' && extra_channels)
		postdata['extra_channels[]'] = extra_channels;

	$.ajax({
		type:'POST',
		url: backend_url,
		data: postdata,
		dataType: 'json',
		success: function(data) {
			var items = data.items.slice(0);
			items.unshift({taggable:false, text: term, replace: term});
			callback(items);
			$(spinelement).hide();
		},
	}).fail(function () {callback([]); }); // Callback must be invoked even if something went wrong.
}
contact_search.cache = {};


function contact_format(item) {
	// Show contact information if not explicitly told to show something else
	if(typeof item.text === 'undefined') {
		var desc = ((item.label) ? item.nick + ' ' + item.label : item.nick);
		if(typeof desc === 'undefined') desc = '';
		if(desc) desc = ' ('+desc+')';
		return "<div class='{0} dropdown-item dropdown-notification clearfix' title='{4}'><img class='menu-img-2' src='{1}' loading='lazy'><span class='font-weight-bold contactname'>{2}</span><span class='dropdown-sub-text'>{4}</span></div>".format(item.taggable, item.photo, item.name, desc, typeof(item.link) !== 'undefined' ? item.link : desc.replace('(','').replace(')',''));
	}
	else
		return "<div>" + item.text + "</div>";
}

function smiley_format(item) {
	return "<div class='dropdown-item'>" + item.icon + ' ' + item.text + "</div>";
}

function bbco_format(item) {
	return "<div class='dropdown-item'>" + item + "</div>";
}

function tag_format(item) {
	return "<div class='dropdown-item'>" + '#' + item.text + "</div>";
}

function editor_replace(item) {
	if(typeof item.replace !== 'undefined') {
		return '$1$2' + item.replace;
	}

	// $2 ensures that prefix (@,@!) is preserved

	return '$1$2{' + item.link + '} ';
}

function basic_replace(item) {
	if(typeof item.replace !== 'undefined')
		return '$1'+item.replace;

	return '$1'+item.name+' ';
}

function link_replace(item) {
	if(typeof item.replace !== 'undefined')
		return '$1'+item.replace;

	return '$1'+item.link+' ';
}

function trim_replace(item) {
	if(typeof item.replace !== 'undefined')
		return '$1'+item.replace;

	return '$1'+item.name;
}

function getWord(text, caretPos) {
	var index = text.indexOf(caretPos);
	var postText = text.substring(caretPos, caretPos+13);
	if (postText.indexOf('[/list]') > 0 || postText.indexOf('[/checklist]') > 0 || postText.indexOf('[/ul]') > 0 || postText.indexOf('[/ol]') > 0 || postText.indexOf('[/dl]') > 0) {
		return postText;
	}
}

function getCaretPosition(ctrl) {
	var CaretPos = 0;   // IE Support
	if (document.selection) {
		ctrl.focus();
		var Sel = document.selection.createRange();
		Sel.moveStart('character', -ctrl.value.length);
		CaretPos = Sel.text.length;
	}
	// Firefox support
	else if (ctrl.selectionStart || ctrl.selectionStart == '0')
		CaretPos = ctrl.selectionStart;
	return (CaretPos);
}

function setCaretPosition(ctrl, pos){
	if(ctrl.setSelectionRange) {
		ctrl.focus();
		ctrl.setSelectionRange(pos,pos);
	}
	else if (ctrl.createTextRange) {
		var range = ctrl.createTextRange();
		range.collapse(true);
		range.moveEnd('character', pos);
		range.moveStart('character', pos);
		range.select();
	}
}

function listNewLineAutocomplete(id) {
	var text = document.getElementById(id);
	var caretPos = getCaretPosition(text)
	var word = getWord(text.value, caretPos);

	if (word != null) {
		var textBefore = text.value.substring(0, caretPos);
		var textAfter  = text.value.substring(caretPos, text.length);
		var textInsert = (word.indexOf('[/dl]') > 0) ? '\r\n[*=] ' : (word.indexOf('[/checklist]') > 0) ? '\r\n[] ' : '\r\n[*] ';
		var caretPositionDiff = (word.indexOf('[/dl]') > 0) ? 3 : 1;

		$('#' + id).val(textBefore + textInsert + textAfter);
		setCaretPosition(text, caretPos + (textInsert.length - caretPositionDiff));
		return true;
	}
	else {
		return false;
	}
}

function string2bb(element) {
	if(element == 'bold') return 'b';
	else if(element == 'italic') return 'i';
	else if(element == 'underline') return 'u';
	else if(element == 'overline') return 'o';
	else if(element == 'strike') return 's';
	else if(element == 'superscript') return 'sup';
	else if(element == 'subscript') return 'sub';
	else if(element == 'highlight') return 'hl';
	else return element;
}

/**
 * jQuery plugin 'editor_autocomplete'
 */
(function( $ ) {
	$.fn.editor_autocomplete = function(backend_url, extra_channels) {

		if(! this.length)
			return;

		if (typeof extra_channels === 'undefined') extra_channels = false;

		// Autocomplete contacts
		contacts = {
			match: /(^|\s)(@\!*)([^ \n]{2,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'c', extra_channels, spinelement=false); },
			replace: editor_replace,
			template: contact_format
		};

		// Autocomplete groups
		groups = {
			match: /(^|\s)(\!\!*)([^ \n]{2,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'f', extra_channels, spinelement=false); },
			replace: editor_replace,
			template: contact_format
		};


		// Autocomplete hashtags
		tags = {
			match: /(^|\s)(\#)([^ \n]{2,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { $.getJSON('/hashtags/' + '?f=&t=' + term).done(function(data) { callback($.map(data, function(entry) { return entry.text.toLowerCase().indexOf(term.toLowerCase()) === 0 ? entry : null; })); }); },
			replace: function(item) { return "$1$2" + item.text + ' '; },
			context: function(text) { return text.toLowerCase(); },
			template: tag_format
		};


		smilies = {
			match: /(^|\s)(:[a-z_:]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { $.getJSON('/smilies/json').done(function(data) { callback($.map(data, function(entry) { return entry.text.indexOf(term) === 0 ? entry : null; })); }); },
			//template: function(item) { return item.icon + item.text; },
			replace: function(item) { return "$1" + item.text + ' '; },
			template: smiley_format
		};


		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			var textcomplete = new Textcomplete(editor);
			textcomplete.register([contacts,groups,smilies,tags], {className:'acpopup', zIndex:1020});
		});


	};
})( jQuery );

/**
 * jQuery plugin 'search_autocomplete'
 */
(function( $ ) {
	$.fn.search_autocomplete = function(backend_url) {

		if(! this.length)
			return;

		// Autocomplete contacts
		contacts = {
			match: /(^@)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'x', [], spinelement='#nav-search-spinner'); },
			replace: basic_replace,
			template: contact_format,
		};

		// Autocomplete groups
		groups = {
			match: /(^\!)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'f', [], spinelement='#nav-search-spinner'); },
			replace: basic_replace,
			template: contact_format
		};

		// Autocomplete hashtags
		tags = {
			match: /(^\#)([^ \n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { $.getJSON('/hashtags/' + '$f=&t=' + term).done(function(data) { callback($.map(data, function(entry) { return entry.text.toLowerCase().indexOf(term.toLowerCase()) === 0 ? entry : null; })); }); },
			replace: function(item) { return "$1" + item.text + ' '; },
			context: function(text) { return text.toLowerCase(); },
			template: tag_format
		};

		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor);
			textcomplete.register([contacts,tags], {className:'acpopup', maxCount:100, zIndex: 1020, appendTo:'nav'});
		});

		textcomplete.on('selected', function() { this.editor.el.form.submit(); });

	};
})( jQuery );

(function( $ ) {
	$.fn.contact_autocomplete = function(backend_url, typ, autosubmit, onselect) {

		if(! this.length)
			return;

		if(typeof typ === 'undefined') typ = '';
		if(typeof autosubmit === 'undefined') autosubmit = false;

		// Autocomplete contacts
		contacts = {
			match: /(^)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, typ,[], spinelement=false); },
			replace: basic_replace,
			template: contact_format,
		};

		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor);
			textcomplete.register([contacts], {className:'acpopup', zIndex:1020});
		});

		if(autosubmit)
			textcomplete.on('selected', function() { this.editor.el.form.submit(); });

		if(typeof onselect !== 'undefined')
			textcomplete.on('select', function() { var item = this.dropdown.getActiveItem(); onselect(item.searchResult.data); });
	};
})( jQuery );

(function( $ ) {
	$.fn.discover_autocomplete = function(backend_url, typ, autosubmit, onselect) {

		if(! this.length)
			return;

		if(typeof typ === 'undefined') typ = 'x';
		if(typeof autosubmit === 'undefined') autosubmit = false;

		// Autocomplete contacts
		contacts = {
			match: /(^)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, typ,[], spinelement=false); },
			replace: link_replace,
			template: contact_format,
		};

		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor);
			textcomplete.register([contacts], {className:'acpopup', zIndex:1020});
		});

		if(autosubmit)
			textcomplete.on('selected', function() { this.editor.el.form.submit(); });

		if(typeof onselect !== 'undefined')
			textcomplete.on('select', function() { var item = this.dropdown.getActiveItem(); onselect(item.searchResult.data); });
	};
})( jQuery );


(function( $ ) {
	$.fn.name_autocomplete = function(backend_url, typ, autosubmit, onselect) {

		if(! this.length)
			return;

		if(typeof typ === 'undefined') typ = '';
		if(typeof autosubmit === 'undefined') autosubmit = false;

		// Autocomplete contacts
		names = {
			match: /(^)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, typ,[], spinelement=false); },
			replace: trim_replace,
			template: contact_format,
		};


		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor);
			textcomplete.register([names], {className:'acpopup', zIndex:1020});
		});

		if(autosubmit)
			textcomplete.on('selected', function() { this.editor.el.form.submit(); });

		if(typeof onselect !== 'undefined')
			textcomplete.on('select', function() { var item = this.dropdown.getActiveItem(); onselect(item.searchResult.data); });

	};
})( jQuery );

(function( $ ) {
	$.fn.bbco_autocomplete = function(type) {

		if(! this.length)
			return;

		if(type=='bbcode') {
			var open_close_elements = ['bold', 'italic', 'underline', 'overline', 'strike', 'superscript', 'subscript', 'quote', 'code', 'open', 'spoiler', 'map', 'nobb', 'list', 'checklist', 'ul', 'ol', 'dl', 'li', 'table', 'tr', 'th', 'td', 'center', 'color', 'font', 'size', 'zrl', 'zmg', 'rpost', 'question', 'answer', 'observer', 'observer.language','embed', 'highlight', 'url', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
			var open_elements = ['observer.baseurl', 'observer.address', 'observer.photo', 'observer.name', 'observer.webname', 'observer.url', '*', 'hr',  ];

			var elements = open_close_elements.concat(open_elements);
		}

		if(type=='comanche') {
			var open_close_elements = ['region', 'layout', 'template', 'theme', 'widget', 'block', 'menu', 'var', 'css', 'js', 'authored', 'comment', 'webpage'];
			var open_elements = [];

			var elements = open_close_elements.concat(open_elements);
		}

		if(type=='comanche-block') {
			var open_close_elements = ['menu', 'var'];
			var open_elements = [];

			var elements = open_close_elements.concat(open_elements);
		}

		bbco = {
			match: /\[(\w*\**)$/,
			search: function (term, callback) {
				callback($.map(elements, function (element) {
					return element.indexOf(term) === 0 ? element : null;
				}));
			},
			index: 1,
			replace: function (element) {
				element = string2bb(element);
				if(open_elements.indexOf(element) < 0) {
					if(element === 'list' || element === 'ol' || element === 'ul') {
						return ['\[' + element + '\]' + '\n\[*\] ', '\n\[/' + element + '\]'];
					} else if(element === 'checklist') {
						return ['\[' + element + '\]' + '\n\[\] ', '\n\[/' + element + '\]'];
					} else if (element === 'dl') {
						return ['\[' + element + '\]' + '\n\[*=Item name\] ', '\n\[/' + element + '\]'];
					} else if(element === 'table') {
						return ['\[' + element + '\]' + '\n\[tr\]', '\[/tr\]\n\[/' + element + '\]'];
					} else if(element === 'observer') {
						return ['\[' + element + '=1\]', '\[/observer\]'];
					} else if(element === 'observer.language') {
						return ['\[' + element + '=en\]', '\[/observer\]'];
					}
					else {
						return ['\[' + element + '\]', '\[/' + element + '\]'];
					}
				}
				else {
					return '\[' + element + '\] ';
				}
			},
			template: bbco_format
		};


		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			var textcomplete = new Textcomplete(editor);
			textcomplete.register([bbco], {className:'acpopup', zIndex:1020});
		});

		this.keypress(function(e){
			if (e.keyCode == 13) {
				var x = listNewLineAutocomplete(this.id);
				if(x) {
					e.stopImmediatePropagation();
					e.preventDefault();
				}
			}
		});
	};
})( jQuery );

