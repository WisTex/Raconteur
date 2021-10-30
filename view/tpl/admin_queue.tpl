<div class="generic-content-wrapper">
<div class="section-title-wrapper"><h3>{{$banner}}</h3></div>
<div class="section-content-wrapper">
{{if $hasentries}}

<table cellpadding="10" id="admin-queue-table"><tr><td>{{$numentries}}&nbsp;&nbsp;</td><td>{{$desturl}}</td><td>{{$priority}}</td><td>&nbsp;</td><td>&nbsp;</td></tr>

{{foreach $entries as $e}}

<tr><td>{{$e.total}}</td><td>{{$e.outq_posturl}}</td><td>{{$e.priority}}</td>{{if $expert}}<td><a href="admin/queue?f=&drophub={{$e.eurl}}" title="{{$nukehub}}" class="btn btn-outline-secondary"><i class="fa fa-times"></i><a></td><td><a href="admin/queue?f=&emptyhub={{$e.eurl}}" title="{{$empty}}" class="btn btn-outline-secondary"><i class="fa fa-trash-o"></i></a></td>{{/if}}</tr>
{{/foreach}}

</table>

{{/if}}
</div>
</div>