
<div id="poke-content" class="generic-content-wrapper">
    <div class="section-title-wrapper">
    <h2>{{$title}}</h2>
    </div>
    <div class="section-content-wrapper">

		<div id="poke-desc">{{$desc}}</div>
		<br>
		<br>

		<form action="poke" method="get">

		<div class="form-group field custom">
			<label for="poke-verb-select" id="poke-verb-lbl">{{$choice}}</label>
			<select class="form-control" name="pokeverb" id="poke-verb-select" >
			{{foreach $verbs as $v}}
			<option value="{{$v.0}}"{{if $v.2}} selected="selected"{{/if}}>{{$v.0}}</option>
			{{/foreach}}
			</select>
		</div>

		<input type="submit" name="submit" value="{{$submit}}" >
		</form>
	</div>
</div>
