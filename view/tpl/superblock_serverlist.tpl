<h3>{{$blocked}}</h3>
<br>
{{if $nothing}}
<div class="descriptive-text">{{$nothing}}</div>
<br>
{{/if}}
{{if $entries}}
<ul style="list-style-type: none;">
{{foreach $entries as $e}}
<li>
<div>
<a class="float-end" href="superblock?f=&unblocksite={{$e.1}}&sectok={{$token}}" title="{{$remove}}"><i class="fa fa-trash"></i></a>
<a href="https://{{$e.0}}">&nbsp;{{$e.0}}</a>
</div>
</li>
{{/foreach}}
</ul>
{{/if}}
