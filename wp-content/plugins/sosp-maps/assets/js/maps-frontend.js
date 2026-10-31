var sosp_map;
var sosp_markers = [];

// Default map center (Denmark center)
var sosp_defaultCenter = [56.155464, 10.211477];

function initializeSOSPMap() {
    var canvas = document.getElementById('sosp-map-canvas');
    if (!canvas) return;

    sosp_map = L.map(canvas, {
        center: sosp_defaultCenter,
        zoom: 7,
        scrollWheelZoom: true
    });

    // Standard OpenStreetMap tiles - no API key; attribution is required by the tile usage policy
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>-bidragydere',
        maxZoom: 19
    }).addTo(sosp_map);

    L.control.scale({ imperial: false }).addTo(sosp_map);

    var icon = L.icon({
        iconUrl: sosp_maps_data.plugin_url + 'assets/images/marker.png',
        iconSize: [40, 56],
        iconAnchor: [20, 56],
        popupAnchor: [0, -50]
    });

    var cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 40
    });

    // Add markers for all groups
    if (sosp_maps_data.groups) {
        sosp_maps_data.groups.forEach(function(group) {
            var marker = sosp_addMarker(group, icon);
            if (marker) cluster.addLayer(marker);
        });
    }

    sosp_map.addLayer(cluster);
}

function sosp_addMarker(group, icon) {
    var lat = parseFloat(group.lat);
    var lng = parseFloat(group.lng);
    if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) return null;

    var marker = L.marker([lat, lng], {
        icon: icon,
        title: group.navn
    });

    // Create popup content
    var content = '<div class="sosp-info-window">' +
        '<h3><a href="' + escapeHtml(group.side) + '" target="_blank">' + escapeHtml(group.navn) + '</a></h3>' +
        '<hr>' +
        '<p><strong>Adresse:</strong><br>' + escapeHtml(group.adresse) + '</p>' +
        '<hr>' +
        '<div class="sosp-kontakt">' +
        '<a href="mailto:' + escapeHtml(group.mail) + '"><strong>Kontakt</strong></a>' +
        '</div>' +
        '</div>';

    marker.bindPopup(content, { minWidth: 250 });

    sosp_markers.push(marker);
    return marker;
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
    return String(text).replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

// Initialize map when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSOSPMap();
});
