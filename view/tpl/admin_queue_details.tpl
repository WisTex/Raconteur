<div class="generic-content-wrapper">
<div class="section-title-wrapper"><h3>{{$banner}}</h3></div>
<div class="section-content-wrapper">
{{if $logs}}
    {{foreach $logs as $log}}
        <pre>{{$log.outq_log}}</pre>
    {{/foreach}}
{{else}}
    {{$nothing}}
{{/if}}
</div>
</div>
