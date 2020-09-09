<div id="projects-sidebar" class="widget">
	<h3>{{$title}}</h3>
	<div id="projects-sidebar-desc">{{$desc}}</div>
	
	<ul class="nav nav-pills flex-column">
		<li class="nav-item"><a href="{{$base}}" class="nav-link{{if $sel_all}} active{{/if}}">{{$all}}</a></li>
		{{foreach $terms as $term}}
		<li class="nav-item"><a  href="{{$base}}?project={{$term.name|urlencode}}" class="nav-link{{if $term.selected}} active{{/if}}">{{$term.cname}}</a></li>
		{{/foreach}}
	</ul>
</div>
