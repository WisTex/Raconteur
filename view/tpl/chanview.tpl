{{if $photo}}
<img style="max-width: 100%;" alt="{{$alt}}" src="{{$photo}}" >
{{/if}}
<center>
<a href="{{$url}}" style="color: white;"><button class="btn btn-primary" style="color: white; margin-top: 50px;"><i class="fa fa-fw fa-external-link"></i>&nbsp;{{$visit}}</button></a>
{{if $outbox}}
<br>
<a href="{{$recentlink}}" style="color: white;"><button class="btn btn-secondary" style="solor: white; margin-top: 10px;"><i class="fa fa-fw fa-eye"></i>&nbsp;{{$view}}</button></a>
{{/if}}
</center>
{{if $about}}
<br>
<br>
{{$about}}
{{/if}}
<br>
<br>
<table style="width: 100%; font-size: 2rem; text-align: center;">
<tr><td style="width: 50%; color: blue;">{{$following_txt}}</td><td style="width: 50%; color: blue;">{{$followers_txt}}</td></tr>
<tr><td>{{$following}}</td><td>{{$followers}}</td></tr>
</table>
