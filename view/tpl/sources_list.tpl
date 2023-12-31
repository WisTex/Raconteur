<div class="generic-content-wrapper">
    <div class="section-title-wrapper">
        <h2>{{$title}}</h2>
    </div>
    <div class="section-content-tools-wrapper">
        <div class="descriptive-text">{{$desc}}</div>

        <div class="sources-links">
            <a href="sources/new">{{$new}}</a>
        </div>

        {{if $sources}}
        <ul class="sources-list">
        {{foreach $sources as $source}}
        <li><a href="sources/{{$source.src_id}}">{{$source.xchan_name}}</a></li>
        {{/foreach}}
        </ul>
        {{/if}}
    </div>
</div>
