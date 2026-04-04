var sosp_map;
var sosp_markers = [];
var sosp_infoWindows = [];

// Default map center (Denmark center)
var sosp_defaultCenter = {lat: 56.155464, lng: 10.211477};

function initializeSOSPMap() {
    var mapOptions = {
        zoom: 7,
        center: sosp_defaultCenter,
        disableDefaultUI: true,
        scaleControl: true,
        draggable: true,
        scrollwheel: true,
        styles: sosp_getMapStyles()
    };
    
    sosp_map = new google.maps.Map(
        document.getElementById('sosp-map-canvas'),
        mapOptions
    );
    
    // Add markers for all groups
    if (sosp_maps_data.groups) {
        sosp_maps_data.groups.forEach(function(group) {
            sosp_addMarker(group);
        });
    }
}

function sosp_addMarker(group) {
    var markerPosition = new google.maps.LatLng(group.lat, group.lng);
    
    var marker = new google.maps.Marker({
        position: markerPosition,
        map: sosp_map,
        icon: sosp_maps_data.plugin_url + 'assets/images/marker.png',
        title: group.navn
    });
    
    // Create info window content
    var content = '<div class="sosp-info-window">' +
        '<h3><a href="' + escapeHtml(group.side) + '" target="_blank">' + escapeHtml(group.navn) + '</a></h3>' +
        '<hr>' +
        '<p><strong>Adresse:</strong><br>' + escapeHtml(group.adresse) + '</p>' +
        '<hr>' +
        '<div class="sosp-kontakt">' +
        '<a href="mailto:' + escapeHtml(group.mail) + '"><strong>Kontakt</strong></a>' +
        '</div>' +
        '</div>';
    
    var infowindow = new google.maps.InfoWindow({
        content: content
    });
    
    marker.addListener('click', function() {
        // Close all other info windows
        sosp_infoWindows.forEach(function(iw) {
            iw.close();
        });
        infowindow.open(sosp_map, marker);
    });
    
    sosp_markers.push(marker);
    sosp_infoWindows.push(infowindow);
}

function sosp_getMapStyles() {
    return [
        {
            "featureType": "road.highway",
            "elementType": "geometry",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "road.arterial",
            "elementType": "geometry",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "poi",
            "elementType": "geometry",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "transit",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "water",
            "elementType": "geometry.fill",
            "stylers": [
                {"color": "#002b58"}
            ]
        },
        {
            "featureType": "road",
            "stylers": [
                {"saturation": -100}
            ]
        },
        {
            "featureType": "landscape",
            "stylers": [
                {"color": "#e6e6e6"}
            ]
        },
        {
            "featureType": "administrative.locality",
            "elementType": "labels",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "landscape",
            "elementType": "labels",
            "stylers": [
                {"visibility": "off"}
            ]
        },
        {
            "featureType": "road",
            "elementType": "labels",
            "stylers": [
                {"visibility": "off"}
            ]
        }
    ];
}

function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

// Initialize map when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSOSPMap();
});
