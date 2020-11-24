<div id="projects-sidebar" class="widget">
	<h3>{{$title}}</h3>
	<div id="projects-sidebar-desc">{{$desc}}</div>
	
	<ul class="nav nav-pills flex-column">
		<li class="nav-item"><a href="{{$base}}" class="nav-link{{if $sel_all}} active{{/if}}">{{$all}}</a></li>
		{{foreach $terms as $term}}
		<li class="nav-item"><a  href="{{$base}}?project={{$term.name|urlencode}}" class="nav-link{{if $term.selected}} active{{/if}}">{{if $term.type == 0}}<strong>{{$term.cname}}</strong>{{else}}{{$term.cname}}{{/if}}</a></li>
		{{/foreach}}
	</ul>
</div>
