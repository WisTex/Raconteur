Multicode
=========


Multicode is a unique feature to this family of communication applications. It allows one to easily create rich-text and multi-media content without caring about the underlying format. Content may be created using HTML, Markdown, or BBcode - or all of the above.

[code]
<b>This text should be bold.</b> (HTML < 5)
<strong>This should be also.</strong> (HTML5)
**So should this.** (Markdown)
[b]And this also.[/b] (BBcode)
[/code]

<b>This text should be bold.</b> (HTML < 5)
<strong>This should be also.</strong> (HTML5)
**So should this.** (Markdown)
[b]And this also.[/b] (BBcode)

Implementation notes:

To avoid surprises, please use preview when attempting something complex. To preview your post without publishing it, click the 'eye' icon below the post or comment. A preview of the post will show up just below the editor region. Click the 'eye' icon again to update the preview at any time. A preview will automatically be generated whenever multi-media content is added via upload/attach or when inserting webpages via the link tool.  

Markdown is very susceptible to "false positives" which may add markup instructions unintentionally when using punctuation characters in unusual or uncommon ways. This can happen (for instance) if you include computer code in a post without identifying it as a block of code. If a markdown sequence is not rendered correctly, try adding space around the triggering punctuation and ensure that all computer code is placed in code blocks.

Markdown has two methods for creating code blocks. One of them is to wrap the code block with 3 backticks (```) alone on a line before and after the code. The second is to indent the text 4 spaces or one tab width. This second form causes a lot of false positives in otherwise normal text and for that reason is not supported here.  
HTML must be heavily filtered on multi-user web applications to avoid/prevent a type of bad behaviour called "Cross-site scripting". You may not use Javascript at all, and there are a number of restrictions placed on rich-media elements such as audio/video tags and cross-domain content such as iframes. We use bbcode internally for many of these constructs - which will become apparent if you include a video or other rich media link. You may wish to do the same. 

Also be aware that all line breaks in your posts are normally preserved and this may affect the display of HTML lists and tables. When using HTML to create such elements, you may be tempted to make them look "neat" by placing each table or list element on its own line. This will not usually display correctly and could include a number of extraneous blank lines, due to the preserved line breaks. For best results when creating lists and tables in HTML, each HTML list element or table row should butt up against the one before it without including any line breaks in between them.  
