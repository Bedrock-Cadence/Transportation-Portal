// FILE: /public_html/assets/js/config.js

document.addEventListener('DOMContentLoaded', function() {
    const facilitySelect = document.getElementById('facility-select');
    const carrierSelect = document.getElementById('carrier-select');
    const configFormSection = document.getElementById('config-form-section');
    
    function fetchEntityConfig(entityType, entityId) {
        if (!entityId || !entityType) {
            configFormSection.style.display = 'none';
            return;
        }

        // Add a loading indicator for better UX
        document.getElementById('entity-name-header').textContent = 'Loading...';

        fetch(`/api/get_entity_config.php?entity_type=${entityType}&entity_id=${entityId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok.');
                return response.json();
            })
            .then(data => {
                populateForm(data);
                configFormSection.style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching entity data:', error);
                alert('An error occurred while loading entity data. Please try again.');
                configFormSection.style.display = 'none';
            });
    }

    function populateForm(data) {
        // --- General Info ---
        document.getElementById('entity-name-header').textContent = data.name;
        document.getElementById('entity-id-input').value = data.id;
        document.getElementById('entity-type-input').value = data.type;
        
        const facilityForm = document.getElementById('facility-settings-form');
        const carrierForm = document.getElementById('carrier-settings-form');
        const config = data.config || {};

        // --- Show/Hide Correct Form Section ---
        facilityForm.style.display = data.type === 'facility' ? 'block' : 'none';
        carrierForm.style.display = data.type === 'carrier' ? 'block' : 'none';

        if (data.type === 'facility') {
            // --- Populate Facility Fields ---
            facilityForm.querySelector('[name="short_bid_duration"]').value = config.short_bid_duration || '15';
            facilityForm.querySelector('[name="long_bid_duration"]').value = config.long_bid_duration || '30';
            facilityForm.querySelector('[name="secure_email"]').value = config.secure_email || '';

            // Populate multi-select for preferred carriers
            const preferredCarriers = config.preferred_carriers || [];
            Array.from(facilityForm.querySelector('[name="preferred_carriers[]"]').options).forEach(option => {
                option.selected = preferredCarriers.includes(parseInt(option.value));
            });

            // Populate multi-select for blacklisted carriers
            const blacklistedCarriers = config.blacklisted_carriers || [];
            Array.from(facilityForm.querySelector('[name="blacklisted_carriers[]"]').options).forEach(option => {
                option.selected = blacklistedCarriers.includes(parseInt(option.value));
            });
            
        } else if (data.type === 'carrier') {
            // --- Populate Carrier Fields ---
            carrierForm.querySelector('[name="secure_email"]').value = config.secure_email || '';
            carrierForm.querySelector('[name="ltd_miles"]').value = config.ltd_miles || '150';

            // Populate multi-select for preferred facilities
            const preferredFacilities = config.preferred_facilities || [];
            Array.from(carrierForm.querySelector('[name="preferred_facilities[]"]').options).forEach(option => {
                option.selected = preferredFacilities.includes(parseInt(option.value));
            });

            // Populate multi-select for blacklisted facilities
            const blacklistedFacilities = config.blacklisted_facilities || [];
            Array.from(carrierForm.querySelector('[name="blacklisted_facilities[]"]').options).forEach(option => {
                option.selected = blacklistedFacilities.includes(parseInt(option.value));
            });

            // Populate checkboxes for trip types
            const tripTypes = config.trip_types || [];
            carrierForm.querySelector('[name="trip_types[]"][value="Stretcher"]').checked = tripTypes.includes('Stretcher');
            carrierForm.querySelector('[name="trip_types[]"][value="Wheelchair"]').checked = tripTypes.includes('Wheelchair');
            
            // Populate checkboxes for special equipment
            const specialEquipment = config.special_equipment || [];
            carrierForm.querySelector('[name="special_equipment[]"][value="O2"]').checked = specialEquipment.includes('O2');
            carrierForm.querySelector('[name="special_equipment[]"][value="IV"]').checked = specialEquipment.includes('IV');
            carrierForm.querySelector('[name="special_equipment[]"][value="Vent"]').checked = specialEquipment.includes('Vent');
            carrierForm.querySelector('[name="special_equipment[]"][value="ECMO"]').checked = specialEquipment.includes('ECMO');
            carrierForm.querySelector('[name="special_equipment[]"][value="Cardiac Monitor"]').checked = specialEquipment.includes('Cardiac Monitor');
        }
    }

    if (facilitySelect) {
        facilitySelect.addEventListener('change', (e) => {
            if (e.target.value) {
                if (carrierSelect) carrierSelect.value = '';
                fetchEntityConfig('facility', e.target.value);
            } else {
                configFormSection.style.display = 'none';
            }
        });
    }
    
    if (carrierSelect) {
        carrierSelect.addEventListener('change', (e) => {
            if (e.target.value) {
                if (facilitySelect) facilitySelect.value = '';
                fetchEntityConfig('carrier', e.target.value);
            } else {
                configFormSection.style.display = 'none';
            }
        });
    }
});