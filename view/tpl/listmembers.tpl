<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>
	<div class="section-content-wrapper">
		<div id="group-update-wrapper" class="clearfix">
			<div id="group" class="list-group">
				<div id="group-members" class="contact_list">
				{{foreach $members as $c}} {{$c}} {{/foreach}}
				</div>
			</div>
		</div>
	</div>
</div>
