<?xml version="1.0" encoding="utf-8" ?>
<feed xmlns="http://www.w3.org/2005/Atom"
  xmlns:thr="http://purl.org/syndication/thread/1.0"
  xmlns:at="http://purl.org/atompub/tombstones/1.0"
  xmlns:media="http://purl.org/syndication/atommedia">

  <id>{{$feed_id}}</id>
  <title>{{$feed_title}}</title>
  <subtitle>{{$feed_title}}</subtitle>
  <generator uri="{{$generator_uri}}" version="{{$version}}">{{$generator}}</generator>
  {{if $profile_page}}
  <link rel="alternate" type="text/html" href="{{$profile_page}}" />
  {{/if}}
{{if $author}}
{{$author}}
{{/if}}

  <updated>{{$feed_updated}}</updated>

