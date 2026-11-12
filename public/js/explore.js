document.addEventListener('DOMContentLoaded', () => {

    const { locations, centers, selected, coordinates } = window.exploreData;

    const countrySelect = document.getElementById('country');
    const govSelect = document.getElementById('governorate');
    const citySelect = document.getElementById('city');
    const statusText = document.getElementById('location-set');

    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const latitudeDisplay = document.getElementById('latitude-display');
    const longitudeDisplay = document.getElementById('longitude-display');

    // ---------- Dropdowns ----------

    function fill(select, items, chosen) {
        select.innerHTML = '';
        items.forEach(item => {
            select.add(new Option(item, item, false, item === chosen));
        });
    }

    function updateStatus() {
        if (countrySelect.value && govSelect.value && citySelect.value) {
            statusText.textContent =
                `Location set: ${citySelect.value}, ${govSelect.value}, ${countrySelect.value}`;
        } else {
            statusText.textContent = '';
        }
    }

    function loadCities(chosenCity) {
        const governorates = locations[countrySelect.value] || {};
        const cities = governorates[govSelect.value] || [];
        fill(citySelect, cities, chosenCity);
        updateStatus();
    }

    function loadGovernorates(chosenGov, chosenCity) {
        const governorates = Object.keys(locations[countrySelect.value] || {});
        fill(govSelect, governorates, chosenGov);
        loadCities(chosenCity);
    }

    fill(countrySelect, Object.keys(locations), selected.country);
    loadGovernorates(selected.governorate, selected.city);

    // ---------- Map ----------

    const map = L.map('farm-map');

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    let marker = null;

    function setLocation(lat, lng) {
        latitudeInput.value = lat.toFixed(7);
        longitudeInput.value = lng.toFixed(7);
        latitudeDisplay.textContent = lat.toFixed(7);
        longitudeDisplay.textContent = lng.toFixed(7);

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const position = marker.getLatLng();
                setLocation(position.lat, position.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
    }

    // لما المحافظة تتغير، الخريطة والـ pin يروحوا لمركزها
    function goToGovernorate() {
        const center = centers[govSelect.value];
        if (!center) return;

        map.setView([center.lat, center.lng], center.zoom);
        setLocation(center.lat, center.lng);
    }

    countrySelect.addEventListener('change', () => {
        loadGovernorates(null, null);
        goToGovernorate();
    });

    govSelect.addEventListener('change', () => {
        loadCities(null);
        goToGovernorate();
    });

    citySelect.addEventListener('change', updateStatus);

    map.on('click', event => {
        setLocation(event.latlng.lat, event.latlng.lng);
    });

    document.getElementById('use-my-location').addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Your browser does not support location detection.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                map.setView([lat, lng], 16);
                setLocation(lat, lng);
            },
            error => {
                const messages = {
                    1: 'Location permission was denied.',
                    2: 'Your location is unavailable.',
                    3: 'Location request timed out.'
                };
                alert(messages[error.code] || 'Unable to get your location.');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    });

    // ---------- أول تحميل ----------

    const savedLatitude = Number(coordinates.latitude);
    const savedLongitude = Number(coordinates.longitude);

    const hasSavedLocation =
        coordinates.latitude !== null && coordinates.longitude !== null &&
        coordinates.latitude !== '' && coordinates.longitude !== '' &&
        Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude) &&
        savedLatitude >= -90 && savedLatitude <= 90 &&
        savedLongitude >= -180 && savedLongitude <= 180;

    if (hasSavedLocation) {
        map.setView([savedLatitude, savedLongitude], 15);
        setLocation(savedLatitude, savedLongitude);
    } else {
        goToGovernorate();
    }

    setTimeout(() => map.invalidateSize(), 200);
});