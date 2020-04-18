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
<a class="pull-right" href="superblock?f=&unblocksite={{$e.1}}&sectok={{$token}}" title="{{$remove}}"><i class="fa fa-trash"></i></a>
<a class="zid" href="{{$e.0}}">&nbsp;{{$e.0}}</a>
</div>
</li>
{{/foreach}}
</ul>
{{/if}}
