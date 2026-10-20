// SmartEcoTrack - Map Functionality

class SmartEcoMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.map = null;
        this.markers = [];
        this.circles = [];
        this.options = Object.assign({
            center: [14.5995, 120.9842], // Manila
            zoom: 12,
            showControls: true
        }, options);
        
        this.init();
    }
    
    init() {
        // Initialize map
        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            zoomControl: this.options.showControls
        });
        
        // Add tile layer with forest theme
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> | SmartEcoTrack',
            maxZoom: 19
        }).addTo(this.map);
        
        // Add scale control
        L.control.scale().addTo(this.map);
        
        // Add custom controls
        this.addCustomControls();
        
        // Load incidents
        this.loadIncidents();
        
        // Add click handler for reporting
        this.map.on('click', (e) => this.onMapClick(e));
    }
    
    addCustomControls() {
        // Custom legend control
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = () => {
            const div = L.DomUtil.create('div', 'map-legend');
            div.innerHTML = `
                <div class="legend-title">Incident Severity</div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #4CAF50;"></span> Low
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #FFA000;"></span> Medium
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #FF5722;"></span> High
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #D32F2F;"></span> Critical
                </div>
            `;
            return div;
        };
        legend.addTo(this.map);
        
        // Location control
        const locationControl = L.control({ position: 'topleft' });
        locationControl.onAdd = () => {
            const button = L.DomUtil.create('button', 'map-location-btn');
            button.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
            button.title = 'My Location';
            button.onclick = () => this.centerOnUser();
            return button;
        };
        locationControl.addTo(this.map);
    }
    
    async centerOnUser() {
        try {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.map.setView([position.coords.latitude, position.coords.longitude], 15);
                        
                        // Add user marker
                        if (this.userMarker) {
                            this.map.removeLayer(this.userMarker);
                        }
                        
                        this.userMarker = L.marker([position.coords.latitude, position.coords.longitude], {
                            icon: L.divIcon({
                                className: 'user-location-marker',
                                html: '<i class="fas fa-user-circle"></i>',
                                iconSize: [30, 30]
                            })
                        }).addTo(this.map)
                            .bindPopup('Your Location')
                            .openPopup();
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        alert('Could not get your location. Please enable location services.');
                    }
                );
            }
        } catch (error) {
            console.error('Error getting location:', error);
        }
    }
    
    loadIncidents() {
        fetch('api/get_active_incidents.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.incidents) {
                    this.clearIncidents();
                    data.incidents.forEach(incident => this.addIncident(incident));
                }
            })
            .catch(error => console.error('Error loading incidents:', error));
    }
    
    addIncident(incident) {
        // Marker color based on severity
        const colors = {
            'low': '#4CAF50',
            'medium': '#FFA000',
            'high': '#FF5722',
            'critical': '#D32F2F'
        };
        
        const color = colors[incident.severity] || '#4CAF50';
        
        // Custom icon
        const icon = L.divIcon({
            className: 'incident-marker',
            html: `<div style="background: ${color}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        // Add marker
        const marker = L.marker([incident.latitude, incident.longitude], { icon })
            .addTo(this.map)
            .bindPopup(this.createPopupContent(incident));
        
        this.markers.push(marker);
        
        // Add circle
        const circle = L.circle([incident.latitude, incident.longitude], {
            color: color,
            fillColor: color,
            fillOpacity: 0.2,
            radius: 500
        }).addTo(this.map);
        
        this.circles.push(circle);
        
        // Cluster if many markers
        if (this.markers.length > 10 && !this.clusterGroup) {
            this.initClustering();
        }
    }
    
    createPopupContent(incident) {
        const date = new Date(incident.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        return `
            <div class="incident-popup">
                <h5>${incident.title}</h5>
                <p><strong>Category:</strong> ${incident.category}</p>
                <p><strong>Severity:</strong> 
                    <span class="badge" style="background: ${this.getSeverityColor(incident.severity)}">
                        ${incident.severity}
                    </span>
                </p>
                <p><strong>Status:</strong> ${incident.status}</p>
                <p><strong>Reported:</strong> ${date}</p>
                <a href="incident_view.php?id=${incident.id}" class="btn btn-sm btn-success">
                    View Details
                </a>
            </div>
        `;
    }
    
    getSeverityColor(severity) {
        const colors = {
            'low': '#4CAF50',
            'medium': '#FFA000',
            'high': '#FF5722',
            'critical': '#D32F2F'
        };
        return colors[severity] || '#4CAF50';
    }
    
    onMapClick(e) {
        // Trigger custom event for reporting
        if (this.onReportLocation) {
            this.onReportLocation(e.latlng);
        }
        
        // Show click marker
        if (this.clickMarker) {
            this.map.removeLayer(this.clickMarker);
        }
        
        this.clickMarker = L.marker([e.latlng.lat, e.latlng.lng], {
            icon: L.divIcon({
                className: 'click-marker',
                html: '<i class="fas fa-map-marker-alt" style="color: #D32F2F; font-size: 24px;"></i>',
                iconSize: [24, 24],
                iconAnchor: [12, 24]
            })
        }).addTo(this.map)
            .bindPopup('Selected Location<br><small>Click to report incident here</small>')
            .openPopup();
    }
    
    clearIncidents() {
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.circles.forEach(circle => this.map.removeLayer(circle));
        this.markers = [];
        this.circles = [];
    }
    
    refreshIncidents() {
        this.loadIncidents();
    }
    
    setReportHandler(handler) {
        this.onReportLocation = handler;
    }
}

// Initialize map when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        window.smartEcoMap = new SmartEcoMap('map');
        
        // Set up report handler if on create report page
        if (document.getElementById('report-form')) {
            window.smartEcoMap.setReportHandler((latlng) => {
                document.getElementById('latitude').value = latlng.lat.toFixed(6);
                document.getElementById('longitude').value = latlng.lng.toFixed(6);
                
                // Update location name using reverse geocoding
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.display_name) {
                            document.getElementById('location_name').value = data.display_name;
                        }
                    })
                    .catch(error => console.error('Reverse geocoding error:', error));
            });
        }
    }
});