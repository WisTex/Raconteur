Location Services
=================

Location services are tools to use location information in fediverse posts. There are a number of different tools.

An important location tool is a *Map Provider addon*, which displays embedded maps for location targets. Currently, one Map Provider addon is provided - "openstreetmap". Only one Map Provider addon can be installed/active on the system at any time, so if somebody creates a Google Map Provider addon and you wish to use that on your site, the openstreetmap addon must be disabled.

Location data may be represented by a text string "49 Main street, Oakvale" or geographic coordinates such as "-32.683,147.78". It is important to know that text locations may be ambiguous, as there is a  "Santa Cruz" in California, USA and also Bolivia. As a result, many of this software's location services require the use of geographic coordinates - due to their precision. Maps can be displayed for text locations, but please use coordinates to obtain full access to the available location tools. If something resembling map coordinates are present in the text location field, an attempt will be made to extract them. 

## Setting location

Location settings are on the main Settings page. First select Settings from the main menu ("hamburger menu" at the top right of the page).

![screenshot showing location settings]([baseurl]/doc/guide/en/locationsettings.png)

The available settings are as follows:

### Default post location
Set this to a text based location if you wish. This will be attached to all your posts and comments. Some fediverse software may attempt to display this location if it is relatively unambiguous. Often used to indicate the region or country you are posting from.

### Obtain post location from your web browser or device
If this option is enabled, your web browser will ask your permission to obtain your device geographic coordinates every time you post something. You may also approve this to  happen automatically. This uses your browser location, which is generally quite accurate on mobile and handheld devices, and is often wildly incorrect (in many countries) if you are using a desktop computer.

### Over-ride your web browser or device and use these coordinates (latitude,longitude)
This setting is often used on desktop computers, allowing you to over-ride the browser location if it is incorrect. The input should in the format "latitude,longitude" and should consist of two floating point numbers representing your geographic coordinates. If both numbers evaluate to 0, location services will not be enabled, despite the fact that this is a valid location off the coast of Africa.


## Using location in posts
Location services make use of several buttons in the post editor. These are show in the following diagram:

![diagram showing location service buttons in post editor]([baseurl]/doc/guide/en/locationwitheditor.png)

Some or all of these buttons may be missing and will only be available if a coordinate-based location has already been provided.

1. The first button (represented as a globe) will always be shown. This allows you to set or change your current location. A text input field is provided. You may provide geographic coordinates in the "latitude,longitude" format **or** you can enter a period '.' which instructs your browser to ask you just this once to insert your current device location into the current post.

2. The next button (represented by an empty circle) is offered any time location data has been inserted into the current post. Clicking this button removes your location information from the post.

3. The next two buttons display any time geographic location is available in the post. They are "enter" and "leave" icons and are used here to to turn the current post into a "checkin" (Arrive) network activity or a "checkout" (Leave) activity. When either of these options are selected, the icon will change colour to indicate that the post is now a checkin/checkout activity and a map will be inserted into the post. You may add additional text or basically any content. Use the preview ("eye" icon) button to view the results without publishing or click 'Share' to share the post. Clicking one of these buttons a second time undoes the action and turns it back into a normal post.

![example post preview of a checkin activity]([baseurl]/doc/guide/en/checkin.png)

## Distance search
Any post which contains geographic coordinates can be used to search by distance. This allows you to quickly see (for instance) restaurant reviews by performing a distance search on any post near that restaurant. To perform a distance search, click on the avatar or photo of the post author. This opens a popup mini-panel with a number of options. If the post contains coordinates, one of these options will be "Nearby", which performs a distance search based on that post.

![screenshot showing how to navigate to distance search]([baseurl]/doc/guide/en/nearby.png)
