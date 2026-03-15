document.addEventListener('DOMContentLoaded', function () {
    // 1. Get DB Param
    const urlParams = new URLSearchParams(window.location.search);
    const dbName = urlParams.get('db') || urlParams.get('dbname');

    // Update Add New Item Link
    const addBtn = document.querySelector('button[onclick*="menu-items.html"]');
    if (addBtn && dbName) {
        addBtn.onclick = function () {
            window.location.href = `menu-items.html?db=${dbName}`;
        };
    }

    // Update Sidebar Links AND Logo to persist DB param
    const sidebarLinks = document.querySelectorAll('.sidebar-link, .logo');
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '#' && dbName) {
            if (href.includes('?')) {
                link.href = `${href}&db=${dbName}`;
            } else {
                link.href = `${href}?db=${dbName}`;
            }
        }
    });

    // 2. Weblink Display
    if (dbName) {
        const weblinkContainer = document.getElementById('weblink-container');
        const weblinkUrl = document.getElementById('weblink-url');

        // Extract hotel name from db name (vingo_hotel_name)
        let hotelName = dbName.replace('vingo_', '').replace(/_/g, '-');

        // Construct URL
        const currentPath = window.location.pathname;
        const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
        const publicUrl = `${window.location.origin}${basePath}/digital-menu.html?db=${dbName}`; // Pass full DB name for simplicity

        weblinkUrl.href = publicUrl;
        weblinkUrl.textContent = publicUrl;
        weblinkContainer.style.display = 'flex';
    }

    // 3. Fetch Items
    fetchItems();

    function fetchItems() {
        let apiUrl = 'api/items.php';
        if (dbName) {
            apiUrl += `?db=${dbName}`;
        }

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                renderTable(data);
                updateStats(data);
            })
            .catch(error => {
                console.error('Error fetching items:', error);
                document.getElementById('menu-table-body').innerHTML = '<tr><td colspan="6" style="text-align:center; color:red;">Failed to load items.</td></tr>';
            });
    }

    function renderTable(items) {
        const tbody = document.getElementById('menu-table-body');
        tbody.innerHTML = '';

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No items found. Add one!</td></tr>';
            return;
        }

        items.forEach(item => {
            const tr = document.createElement('tr');

            // Availability Class
            const availClass = item.availability.toLowerCase().includes('lunch') ? 'Lunch' : item.availability;

            // Status Badge
            const statusBadge = item.seasonal == 1
                ? '<span class="status-badge status-active">Seasonal</span>'
                : '<span class="status-badge status-inactive">Regular</span>';

            tr.innerHTML = `
                <td>
                    <div class="item-info">
                        <div class="item-img" style="background: ${item.image_color || '#333'};">
                        </div>
                        <span>${item.name}</span>
                    </div>
                </td>
                <td>${item.category}</td>
                <td>
                    <input type="number" value="${item.price}" class="price-input" readonly>
                </td>
                <td>
                    <span class="avail-text">${item.availability}</span>
                </td>
                <td>
                    ${statusBadge}
                </td>
                <td>
                    <button class="action-btn" onclick="deleteItem(${item.id})">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #F87171;">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateStats(items) {
        document.getElementById('total-items-count').textContent = items.length;
    }

    // filtering logic (adapted)
    const categorySelect = document.querySelector('.filters select:nth-of-type(1)');
    const availabilitySelect = document.querySelector('.filters select:nth-of-type(2)');
    const searchInput = document.querySelector('.search-box input');

    function filterItems() {
        const categoryValue = categorySelect.value.toLowerCase();
        const availabilityValue = availabilitySelect.value.toLowerCase();
        const searchValue = searchInput.value.toLowerCase();
        const tableRows = document.querySelectorAll('#menu-table-body tr');

        tableRows.forEach(row => {
            if (row.children.length < 2) return; // Skip empty/loading rows

            const itemName = row.querySelector('.item-info span').textContent.toLowerCase();
            const category = row.children[1].textContent.trim().toLowerCase();
            const availabilityText = row.querySelector('.avail-text');
            const availability = availabilityText ? availabilityText.textContent.trim().toLowerCase() : '';

            const matchesCategory = categoryValue === '' || category === categoryValue;
            const matchesAvailability = availabilityValue === '' || availability.includes(availabilityValue);
            const matchesSearch = itemName.includes(searchValue);

            if (matchesCategory && matchesAvailability && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    categorySelect.addEventListener('change', filterItems);
    availabilitySelect.addEventListener('change', filterItems);
    searchInput.addEventListener('input', filterItems);
});

// exposed function for onclick
function deleteItem(id) {
    if (!confirm('Are you sure you want to delete this item?')) return;

    const urlParams = new URLSearchParams(window.location.search);
    const dbName = urlParams.get('db');

    fetch('api/items.php' + (dbName ? `?db=${dbName}` : ''), {
        method: 'DELETE',
        body: JSON.stringify({ id: id }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(res => res.json())
        .then(data => {
            if (data.message) {
                // Reload
                window.location.reload();
            }
        });
}
