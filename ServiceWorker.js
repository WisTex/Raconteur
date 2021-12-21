// This file should be served from the web root to avoid scope and cookie related issues with some browsers
self.addEventListener('install', function(e) {
	console.log('install event');
});

self.addEventListener('fetch', function(e) {
	// nothing here yet

});
