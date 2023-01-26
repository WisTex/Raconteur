Federated Search
================

The federated search feature was added to provide individual control over the
ability to search your stream and personal content.

Previously this was a site security setting made by the site admin.

## Implementation Details

The first part of this epoch was to enable the fetching of search results as an ActivityStreams 'Collection'. This work was completed some time ago.

The second phase is providing a new permission named 'search_stream'. Only viewers possessing both 'view_stream' and 'search_stream' permission can search your channel content going forward. This permission can be assigned individually, or automatically by channel Permission Role and the personal Roles app.

The underlying search module is also to be changed so that the URL may contain a channel name. In the absence of a channel username in the URL the entire site will be searched, but will only provide results from channels which have permitted search to the current viewer. When a channel username is present, the search is restricted to content authored by that channel.

The channel search URL will be provided in the ActivityPub and Nomad actor records in the optional 'endpoints' section. The names used are 'nomad:searchContent' and 'nomad:searchTags'. These endpoints will be essentially a template, containing a bracket pair {} which is then substituted with the desired search text. If evaluated with the nomad: prefix, the URL is guaranteed to provide results for both HTML and ActivityPub/ActivityStreams/Nomad/Zot6 requests. If used with any other namespace prefix, it is only assumed to provide an ActivityPub compliant endpoint.

If a channel has federated search enabled, a new entry will be provided in the author action drop down attached to every post/comment. This will be named 'Search' (or 'Search Content' and 'Search Tags') and will perform an HTML search of the provided endpoint(s). This entry will only be present if the provided endpoints are associated with the 'nomad:' context namespace.

If a channel has opted out of channel discovery (inclusion in federated directories), no search links will be provided; with the exception of the so-called 'system channel' which represents the site. The system channel will provide these links purely based on the system setting of 'block_public_search'.

## Acceptance Criteria and Testing

Validate the existence of the endpoint across all protocols when the discovery criteria have been met.

Validate that a site search does not contain content created by a member which you do not have permission to search.

Validate that a channel search only contains content created by the requested channel and that matching results are found if content exists containing that search text.

Repeat results testing with hashtag search.

Verify the search results contain the same information whether fetched via HTML or via federated protocol and that the returned data objects are valid for the federated protocol used.

## Data Migration

A database migration may be required to set the initial 'search_stream' permission correctly for existing channels based on the channel permissions role.