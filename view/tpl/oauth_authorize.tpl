<h1>{{$title}}</h1>

<div class='oauthapp'>
	<img src='{{$app.icon}}'>
	<h3>{{$app.name}}</h3>
	<p class="descriptive-paragraph">{{$authorize}}</p>
	<form method="POST">
	<div class="settings-submit-wrapper">
		<input type="hidden" name="client_id" value="{{$client_id}}" />
		<input type="hidden" name="redirect_uri" value="{{$redirect_uri}}" />
		<input type="hidden" name="state" value="{{$state}}" />
		<button class="btn btn-lg btn-danger" name="authorize" value="deny" type="submit">{{$no}}</button>
		<button class="btn btn-lg btn-success" name="authorize" value="allow" type="submit">{{$yes}}</button>
	</div>
	</form>
</div>