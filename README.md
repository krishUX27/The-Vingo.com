Vingo — Admin Portal Landing Page (Admin entrance + Public preview)

This package contains a modern SaaS-style landing page tailored for the Vingo admin portal.
Files:
 - index.html         (landing page)
 - css/style.css      (styles)
 - js/app.js          (interactions; API-ready)
 - assets/logo.svg    (inline SVG used in page)

Usage:
 - Extract the ZIP and serve the folder with any static server (or drop inside your existing project public directory).
 - The login form posts to /api/auth/login (placeholder). Replace with your real auth endpoint.
 - The demo "Fetch Menu" calls GET /api/menu/{business_slug}/{branch_id} — adjust the path if your API is hosted elsewhere.
 - The "Open Public Menu" button links to /menu.php?b=vingo&br=1 which matches the MVP backend created earlier.

Notes:
 - This is a static frontend designed to be integrated into the PHP MVP you already have. It is responsive and uses no external libraries.
 - If you want me to integrate these files into the PHP project (replace the current basic pages), say so and I'll embed them into the codebase and regenerate the ZIP.
