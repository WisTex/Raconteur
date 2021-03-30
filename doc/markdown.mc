Markdown
========

This software application recognises only a limited set of markdown constructs in order to avoid surprises. These usually occur if you enclude computer code or extraneous punctuation in your posts. Usage of the ***preview*** function before submitting a post or comment is encouraged. 

[h3]Bold and italic[/h3]

Bold and italic are only recognised at the beginning of a line or after white space.
If you wish to include the special characters ( \* or \_ ) without interpreting them as markdown, precede them with a backslash '\' character or wrap the desired text in [nobb][nomd]*text to be escaped*[/nomd][/nobb] tags.

[table]
[tr][td]Markdown[/td][td]Result[/td][/tr]
[tr][td][nomd] *italic text* [/nomd][/td][td] *italic text* [/td][/tr]
[tr][td][nomd] _italic text_ [/nomd][/td][td] _italic text_ [/td][/tr]
[tr][td][nomd] **bold text** [/nomd][/td][td] **bold text** [/td][/tr]
[tr][td][nomd] __bold text__ [/nomd][/td][td] __bold text__ [/td][/tr]
[tr][td][nomd] ***bold and italic text*** [/nomd][/td][td] ***bold and italic text*** [/td][/tr]
[tr][td][nomd] ___bold and italic text___ [/nomd][/td][td] ___bold and italic text___ [/td][/tr]

[/table]

[h3]Headers[/h3]

Level headers must occur at the beginning of a line and be separated from the header text by at least one whitescape character. The exact rendering is dependent on the installed theme used and some different level headers may appear to be the same size and may include underlines or other text decoration.

[table]
[tr][td]Markdown[/td][td]Result[/td][/tr]
[tr][td][nomd]Headline text<br>===========[/nomd][/td][td]<h1>Headline text</h1>[/td][/tr]
[tr][td][nomd]# level 1 header[/nomd][/td][td]
# level 1 header[/td][/tr]
[tr][td][nomd]## level 2 header[/nomd][/td][td]
## level 2 header[/td][/tr]
[tr][td][nomd]### level 3 header[/nomd][/td][td]
### level 3 header[/td][/tr]
[tr][td][nomd]#### level 4 header[/nomd][/td][td]
#### level 4 header[/td][/tr]
[tr][td][nomd]##### level 5 header[/nomd][/td][td]
##### level 5 header[/td][/tr]

[/table]

[h3]Code and quotes[/h3]

The markdown specification allows code blocks to be any line beginning with 4 spaces or a tab. This particular syntax rule may produce undesirable results with normal text that wasn't intended to be part of a code block and is not supported in this application. Additionally, inline code must be preceded by at least one space character ***or*** occur at the beginning of a line and may not include line breaks. If you wish to insert backtick characters without triggering a code block, precede them with a backslash character or wrap the text in [nobb][nomd][/nomd][/nobb].

[table]
[tr][td]Markdown[/td][td]Result[/td][/tr]
[tr][td][nomd]
```<br>
This is a code block<br>
spanning multiple lines<br>
```<br>
[/nomd][/td][td]
```
This is a code block
spanning multiple lines
```
[/td][/tr]
[tr][td][nomd]
This is an example of `inline code`.
[/nomd][/td][td]
This is an example of `inline code`.
[/td][/tr]
[tr][td][nomd]
> This is quoted text which may<br>
> span multiple lines.<br>
[/nomd][/td][td]
> This is quoted text which may
> span multiple lines.
[/td][/tr]

[/table]


[h3]Links and images[/h3]

[table]
[tr][td]Markdown[/td][td]Result[/td][/tr]
[tr][td][nomd]
[This is a hyperlink]([baseurl])
[/nomd][/td][td]
[This is a hyperlink]([baseurl])
[/td][/tr]
[tr][td][nomd]
![This is an image]([baseurl]/images/zot-300.png)
[/nomd][/td][td]
![This is an image]([baseurl]/images/zot-300.png)
[/td][/tr]
[/table]


[h3]Lists[/h3]

As with bold and italic, the first character of an unordered list may be preceded by a backslash or wrapped in [nobb][nomd][/nomd][/nobb] tags to prevent normal text lines beginning with these characters from being interpreted as a list. 


[table]
[tr][td]Markdown[/td][td]Result[/td][/tr]
[tr][td][nomd]
* list item 1<br>
* list item 2<br>
* list item 3<br>
[/nomd][/td][td]
* list item 1
* list item 2
* list item 3
[/td][/tr]
[tr][td][nomd]
- list item 1<br>
- list item 2<br>
- list item 3<br>
[/nomd][/td][td]
- list item 1
- list item 2
- list item 3
[/td][/tr]
[tr][td][nomd]
+ list item 1<br>
+ list item 2<br>
+ list item 3<br>
[/nomd][/td][td]
+ list item 1
+ list item 2
+ list item 3
[/td][/tr]
[tr][td][nomd]
1. list item 1<br>
2. list item 2<br>
3. list item 3<br>
[/nomd][/td][td]
1. list item 1
2. list item 2
3. list item 3
[/td][/tr]
[tr][td][nomd]
1) list item 1<br>
2) list item 2<br>
3) list item 3<br>
[/nomd][/td][td]
1) list item 1
2) list item 2
3) list item 3
[/td][/tr]
[/table]
