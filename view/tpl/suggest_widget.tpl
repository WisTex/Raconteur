<div class="widget suggestions-widget">
<h3>{{$title}}</h3>
{{if $entries}}
{{foreach $entries as $child}}
{{include file="suggest_friends.tpl" entry=$child}}
{{/foreach}}
{{/if}}
<div class="clear"></div>
<!--disabled the more link until the zot and zot6 directories are merged due to conflicting results -->
<!--div class="suggest-widget-more"><a href="suggestions">{{$more}}</a></div-->
</div>
