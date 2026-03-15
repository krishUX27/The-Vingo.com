
// Database Management via API (MySQL)
const API_URL = 'api/items.php';

class MenuDatabase {
    constructor() {
        const urlParams = new URLSearchParams(window.location.search);
        this.dbName = urlParams.get('db') || urlParams.get('dbname');

        // Derive Customer Name from DB name if available
        if (this.dbName && this.dbName.startsWith('vingo_')) {
            let raw = this.dbName.replace('vingo_', '').replace(/_/g, ' ');
            // Title Case
            this.customerName = raw.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
        } else {
            this.customerName = "Default Restaurant";
        }
    }

    getApiUrl() {
        if (this.dbName) {
            return `${API_URL}?db=${this.dbName}`;
        }
        return API_URL;
    }

    async getAllItems() {
        try {
            const response = await fetch(this.getApiUrl());
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Error fetching items:', error);
            // Return empty array instead of crashing
            return [];
        }
    }

    getCustomerName() {
        return this.customerName;
    }

    async addItem(item) {
        try {
            // Include db in item payload for db_connection to pick up if needed via POST reading, 
            // though db_connection reads GET/POST/JSON. 
            // We'll also append it to URL for safety since db_connection logic prioritizes GET/POST/JSON.

            const payload = { ...item };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getApiUrl(), { // Append to URL as well
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            return await response.json();
        } catch (error) {
            console.error('Error adding item:', error);
        }
    }

    async updateItem(id, updatedFields) {
        try {
            const payload = { id, ...updatedFields };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getApiUrl(), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating item:', error);
        }
    }

    async deleteItem(id) {
        try {
            const payload = { id };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getApiUrl(), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting item:', error);
        }
    }

    // --- Offers API ---
    getOffersUrl() {
        let url = 'api/offers.php';
        if (this.dbName) {
            return `${url}?db=${this.dbName}`;
        }
        return url;
    }

    async getOffers() {
        try {
            const response = await fetch(this.getOffersUrl());
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Error fetching offers:', error);
            return [];
        }
    }

    async addOffer(offer) {
        try {
            const payload = { ...offer };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getOffersUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return await response.json();
        } catch (error) {
            console.error('Error adding offer:', error);
        }
    }

    async updateOffer(id, updatedFields) {
        try {
            const payload = { id, ...updatedFields };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getOffersUrl(), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating offer:', error);
        }
    }

    async deleteOffer(id) {
        try {
            const payload = { id };
            if (this.dbName) payload.db = this.dbName;

            const response = await fetch(this.getOffersUrl(), {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting offer:', error);
        }
    }
}

// Export instance
const menuDB = new MenuDatabase();
