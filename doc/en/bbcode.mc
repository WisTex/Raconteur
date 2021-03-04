BBcode Reference
================

[h3]Text Decoration[/h3]
[table]
[tr]
[th]BBcode syntax[/th][th]Rendered text[/th]
[/tr]
[tr]
[td][nobb][b]bold[/b][/nobb][/td][td]<strong>bold</strong>[/td]
[/tr]
[tr]
[td][nobb][i]italic[/i][/nobb][/td][td]<em>italic</em>[/td]
[/tr]
[tr]
[td][nobb][u]underlined[/u][/nobb][/td][td]<u>underlined</u>[/td]
[/tr]
[tr]
[td][nobb][s]strike[/s][/nobb][/td][td]<strike>strike</strike>[/td]
[/tr]
[tr]
[td][nobb]super[sup]script[/sup][/nobb][/td][td]super<sup>script</sup>[/td]
[/tr]
[tr]
[td][nobb]sub[sub]script[/sub][/nobb][/td][td]sub<sub>script</sub>[/td]
[/tr]
[tr]
[td][nobb][color=red]red[/color][/nobb][/td][td]<span style="color: red;">red</span>[/td]
[/tr]
[tr]
[td][nobb][hl]highlighted[/hl][/nobb][/td][td]<span style="background-color: yellow;">highlighted</span>[/td]
[/tr]
[tr]
[td][nobb][font=courier]some text[/font][/nobb][/td][td]<span style="font-family: courier;">some text</span>[/td]
[/tr]
[tr]
[td][nobb][quote]quote[/quote][/nobb][/td][td]<blockquote>quote</blockquote>[/td]
[/tr]
[tr]
[td][nobb][quote=Author]Author? Me? No, no, no...[/quote][/nobb][/td]
[td]<strong class="author">Author wrote:</strong><blockquote>Author? Me? No, no, no...</blockquote>[/td]
[/tr]
[tr]
[td][nobb][size=small]small text[/size]&nbsp;
[size=xx-large]xx-large text[/size]&nbsp;
[size=20]20px exactly[/size]&nbsp'
[/nobb]
Size options include: [b]xx-small, small, medium, large, xx-large[/b][/td]
[td]<span style="font-size: small;">small text</span><br><span style="font-size: xx-large;">xx-large text</span><br><span style="font-size: 20px;">20px exactly</span>[/td]
[/tr]
[tr]
[td][nobb]Add a horizontal bar
[hr]
Like this[/nobb][/td]
[td]Add a horizontal bar<br><hr><br>Like this[/td]
[/tr]
[tr]
[td][nobb]This is
[center]centered[/center]
text[/nobb][/td]
[td]
This is<br><div style="text-align:center;">centered</div><br>text
[/td]
[/tr]
[/table]

<h3>Code blocks</h3>
Code can be rendered generically in a block or inline format (depending on if there are new line characters in the text), or you can specify a supported language for enhanced syntax highlighting. Syntax highlighting requires a suitable rendering addon. Supported languages depend on the addon but [i]may[/i] include include <strong>php, css, mysql, sql, abap, diff, html, perl, ruby, vbscript, avrc, dtd, java, xml, cpp, python, javascript, js, json, sh </strong>. 

If a rendering addon is not installed or an unsupported language is specified, the output for syntax highlighted code blocks is the same as the block format code tag.

[table]
[tr]
[th]BBcode syntax[/th][th]Output[/th]
[/tr]
[tr]
[td][nobb][code]function bbcode() { }[/code][/nobb][/td][td]<code>function bbcode() { }</code>[/td]
[/tr]
[tr]
[td][nobb][code=php]
function bbcode() {
  $variable = true;
  if ( $variable ) {
	echo "true";
  }
}
[/code][/nobb][/td]
[td]<code>
function bbcode() {
  $variable = true;
  if ( $variable ) {
	echo "true";
  }
}
</code>[/td]
[/tr]
[tr]
[td][nobb][nobb]This is how [i]you[/i] can 
[u]show[/u] how to use 
[hl]BBcode[/hl] syntax[/nobb][/nobb][/td]
[td][nobb]This is how [i]you[/i] can [u]show[/u] how to use [hl]BBcode[/hl] syntax[/nobb][/td]
[/tr][/table]

[h3]Lists[/h3]
[table]
[tr]
[th]BBcode syntax[/th][th]Rendered list[/th]
[/tr]
[tr]
[td][nobb]
[ul]\
[*] First list element
[*] Second list element
[/ul][/nobb][/td]
[td]<ul class="listbullet" style="list-style-type: circle;"><li> First list element</li><li> Second list element<br></li></ul>[/td]
[/tr]
[tr]
[td][nobb]
[ol]\
[*] First list element
[*] Second list element
[/ol][/nobb][/td]
[td]<ul class="listdecimal" style="list-style-type: decimal;"><li> First list element</li><li> Second list element<br></li></ul>[/td]
[/tr]
[tr]
[td][nobb]
[list=A]\
[*] First list element
[*] Second list element
[/list][/nobb]

The list type options are 1, i, I, a, A.[/td]
[td]<ul class="listupperalpha" style="list-style-type: upper-alpha;"><li> First list element</li><li> Second list element</li></ul>[/td]
[/tr]
[/table]

[h3]Tables[/h3]

[table]
[tr]
[th]BBcode syntax[/th][th]Rendered table[/th]
[/tr]
[tr]
[td][nobb][table border=0]\
[tr][th]Header 1[/th][th]Header 2[/th][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[/table][/nobb]
[/td]
[td]<table class="table"><tbody><tr><th>Header 1</th><th>Header 2</th></tr>
<tr><td>Content</td><td>Content</td></tr><tr><td>Content</td><td>Content</td></tr></tbody></table>
[/td]
[/tr]
[tr]
[td][nobb][table border=1]\
[tr][th]Header 1[/th][th]Header 2[/th][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[/table][/nobb][/td]
[td]<table class="table table-bordered"><tbody><tr><th>Header 1</th><th>Header 2</th></tr>
<tr><td>Content</td><td>Content</td></tr><tr><td>Content</td><td>Content</td></tr></tbody></table>
[/td]
[/tr]
[tr]
[td][nobb][table]\
[tr][th]Header 1[/th][th]Header 2[/th][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[tr][td]Content[/td][td]Content[/td][/tr]\
[/table][/nobb][/td]
[td]<table class="table"><tbody><tr><th>Header 1</th><th>Header 2</th></tr>
<tr><td>Content</td><td>Content</td></tr><tr><td>Content</td><td>Content</td></tr></tbody></table>
[/td]
[/tr]
[/table]

<h3>Links and Embedded Content</h3>

[table]
[tbody]
[tr][th]BBcode syntax[/th][th]Output[/th][/tr]
[tr][td][nobb][video]video URL[/video]<br>
[video poster="image.jpg"]video URL[/video]<br>
[audio]audio URL[/audio]<br>[/nobb][/td]
[td][/td][/tr]
[tr][td][nobb][url=https://zotlabs.com]Zotlabs[/url][/nobb][/td]
[td]<a href="https://zotlabs.com" target="_blank">Zotlabs</a>[/td]
[/tr]
[tr]
[td][nobb]An image
[img]https://example.org/image.jpg[/img]
in some text [/nobb][/td]
[td]An image<br><img src="[baseurl]/images/default_profile_photos/rainbow_man/300.jpg" style="height: 75px; width:75px;" alt="Image/photo"><br>in some text[/td]
[/tr]
[tr]
[td][nobb]An image with alt text
[img alt="photo description"]https://example.org/image.jpg[/img][/nobb][/td]
[td]An image with alt text<br><img src="[baseurl]/images/default_profile_photos/rainbow_man/300.jpg" style="width:75px; width:75px;" title="photo description" alt="photo description">[/td]
[/tr]

[/tbody]
[/table]
	

<h3>$Projectname specific codes</h3>

[table]
[tbody]
[tr][th]BBcode syntax[/th][th]Output[/th][/tr]
[tr][td][nobb]Magic-auth version of [url] tag
[zrl=https://macgirvin.com]Identity-aware link[/zrl][/nobb][/td]
[td][/td][/tr]
[tr]
[td]Magic-auth version of [img] tag
[nobb][zmg]https://hubzilla.org/some/photo.jpg[/zmg][/nobb]
[/td][td]Image is only viewable by those authenticated and with permission.[/td]
[/tr]
[tr]
[td]Observer-dependent output:
[nobb][observer=1]Text to display if observer IS authenticated[/observer][/nobb]
[/td][td][/td]
[/tr]
[tr]
[td]
[nobb][observer=0]Text to display if observer IS NOT authenticated[/observer][/nobb][/td]
[td][/td]
[/tr]
[tr]
[td][nobb][observer.language=en]Text to display if observer language is English[/observer][/nobb][/td]
[td][/td]
[/tr]
[tr]
[td][nobb][observer.language!=de]Text to display if observer language is not German[/observer][/nobb][/td]
[td][/td]
[/tr]
[tr]
[td][nobb][observer.url][/nobb][/td]
[td]channel URL of observer[/td]
[/tr]
[tr]
[td][nobb][observer.baseurl][/nobb][/td]
[td]website of observer[/td]
[/tr]
[tr]
[td][nobb][observer.name][/nobb][/td]
[td]name of observer[/td]
[/tr]
[tr]
[td][nobb][observer.webname][/nobb][/td]
[td]short name in the url of the observer[/td]
[/tr]
[tr]
[td][nobb][observer.address][/nobb][/td]
[td]address (fediverse-id) of observer[/td]
[/tr]
[tr]
[td][nobb][observer.photo][/nobb][/td]
[td]profile photo of observer[/td]
[/tr]
[tr]
[td][nobb]What is a spoiler?
[spoiler]Text you want to hide.[/spoiler][/nobb][/td]
[td]
What is a spoiler? <div onclick="openClose('opendiv-1131603764'); return false;" class="fakelink">Click to open/close</div><blockquote id="opendiv-1131603764" style="display: none;">Text you want to hide.</blockquote>[/td]
[/tr]
[tr]
[td][nobb][rpost=title]Text to post[/rpost][/nobb]
The observer will be returned to their home hub to enter a post with the specified title and body. Both are optional[/td]
[td]<a href="[baseurl]/rpost?f=&amp;title=title&amp;body=Text+to+post" target="_blank">[baseurl]/rpost?f=&amp;title=title&amp;body=Text+to+post</a>[/td]
[/tr]
[tr]
[td]Generate QR code
This requires the <strong>qrator</strong> addon.
[nobb][qr]text to post[/qr][/nobb][/td]
[td][/td]
[/tr]
[tr]
[td]This requires a suitable map addon such as <strong>openstreetmap</strong>.
[nobb][map][/nobb][/td]
[td]Generate an inline map using the current browser coordinates of the poster, if browser location is enabled[/td]
[/tr]
[tr]
[td]This requires a suitable map addon such as <strong>openstreetmap</strong>.
[nobb][map=latitude,longitude][/nobb][/td]
[td]Generate a map using global coordinates.[/td][/tr]
[tr]
[td]This requires a suitable map addon such as <strong>openstreetmap</strong>.
[nobb][map]Place Name[/map][/nobb][/td]
[td]
Generate a map for a given named location. The first matching location is returned. For instance "Sydney" will usually return Sydney, Australia and not Sydney, Nova Scotia, Canada unless the more precise location is specified. It is highly recommended to use the post preview utility to ensure you have the correct location before submitting the post.
[/td]
[/tr]
[tr]
[td][nobb][&amp;&ZeroWidthSpace;copy;][/nobb][/td]
[td] &copy; [/td]
[/tr]
[/tbody][/table]

