function str_rot13 (str) {
  // http://kevin.vanzonneveld.net
  // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // +   improved by: Ates Goral (http://magnetiq.com)
  // +   bugfixed by: Onno Marsman
  // +   improved by: Rafa? Kukawski (http://blog.kukawski.pl)
  // *     example 1: str_rot13('Kevin van Zonneveld');
  // *     returns 1: 'Xriva ina Mbaariryq'
  // *     example 2: str_rot13('Xriva ina Mbaariryq');
  // *     returns 2: 'Kevin van Zonneveld'
  // *     example 3: str_rot13(33);
  // *     returns 3: '33'
	return (str + '').replace(/[a-z]/gi, function (s) {
		return String.fromCharCode(s.charCodeAt(0) + (s.toLowerCase() < 'n' ? 13 : -13));
	});
}

function hz_encrypt(alg, elem) {
	var enc_text = '';
	var newdiv = '';

	if(typeof tinyMCE !== "undefined")
		tinyMCE.triggerSave(false,true);

	var text = $(elem).val();

	// key and hint need to be localised

        var passphrase = prompt(aStr['passphrase']);
        // let the user cancel this dialogue
        if (passphrase == null)
                return false;
        var enc_key = bin2hex(passphrase);

	// If you don't provide a key you get rot13, which doesn't need a key
	// but consequently isn't secure.  

	if(! enc_key)
		alg = 'rot13';

	if((alg == 'rot13') || (alg == 'triple-rot13'))
		newdiv = "[crypt alg='rot13']" + window.btoa(str_rot13(text)) + '[/crypt]';

	if(alg == 'AES-128-CCM') {

			// This is the prompt we're going to use when the receiver tries to open it.
			// Maybe "Grandma's maiden name" or "our secret place" or something. 

			var enc_hint = bin2hex(prompt(aStr['passhint']));

			enc_text = sjcl.encrypt(enc_key, text);

			encrypted = enc_text.toString();

			newdiv = "[crypt alg='AES-128-CCM' hint='" + enc_hint + "']" + window.btoa(encrypted) + '[/crypt]';
	}

	enc_key = '';

	// This might be a comment box on a page with a tinymce editor
	// so check if there is a tinymce editor but also check the display
	// property of our source element - because a tinymce instance
	// will have display "none". If a normal textarea such as in a comment
	// box has display "none" you wouldn't be able to type in it.
	
	if($(elem).css('display') == 'none' && typeof tinyMCE !== "undefined") {
		tinyMCE.activeEditor.setContent(newdiv);
	}
	else {
		$(elem).val(newdiv);
	}

}

function hz_decrypt(alg, hint, text, elem) {

	var dec_text = '';

	var supported = ['AES-128-CCM', 'rot13', 'triple-rot13'];

	if(supported.indexOf(alg) < 0) {
		alert('Sorry, this encryption type is not supported anymore.\r\nConsider asking your admin to install the cryptojs addon for legacy crypto support.');
		return;
	}

	text = window.atob(text);

	if(alg == 'rot13' || alg == 'triple-rot13')
		dec_text = str_rot13(text);
	else {
		var enc_key = bin2hex(prompt((hint.length) ? hex2bin(hint) : aStr['passphrase']));
	}

	if(alg == 'AES-128-CCM') {
		dec_text = sjcl.decrypt(enc_key, text);
	}

	enc_key = '';

	// Not sure whether to drop this back in the conversation display.
	// It probably needs a lightbox or popup window because any conversation 
	// updates could 
	// wipe out the text and make you re-enter the key if it was in the
	// conversation. For now we do that so you can read it.

	var dec_result = dec_text.toString();
	delete dec_text;

	// incorrect decryptions *usually* but don't always have zero length
	// If the person typo'd let them try again without reloading the page
	// otherwise they'll have no "padlock" to click to try again.

	if(dec_result.length) {
		$(elem).html(b2h(dec_result));
		dec_result = '';
	}
}

