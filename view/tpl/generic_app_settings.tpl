<div class="panel">
	<div class="section-subtitle-wrapper" role="tab" id="{{$addon.0}}-settings">
	<form action="{{$addon.0}}" method="post">
		<h3>
			{{if $addon.1|substr:0:1 === '<'}}
			{{$addon.1}}
			{{else}}
			<i class="fa fa-gear"></i> {{$addon.1}}
			{{/if}}
		</h3>
	</div>
	<div id="{{$addon.0}}-settings-content" role="tabpanel" aria-labelledby="{{$addon.0}}-settings" data-bs-parent="#settings">
		<div class="section-content-tools-wrapper">
			{{$content}}
			{{if $addon.0}}
			<div class="settings-submit-wrapper" >
				<button id="{{$addon.0}}-submit" type="submit" name="{{$addon.0}}-submit" class="btn btn-primary" value="{{$addon.3}}">{{$addon.3}}</button>
			</div>
			{{/if}}
		</div>
	</form>
	</div>
</div>
