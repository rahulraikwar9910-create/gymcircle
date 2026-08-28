<?php
// Smart configuration: detects if running inside Docker network
// or falls back to localhost if running natively on Windows.
if (gethostbyname('backend') !== 'backend') {
    define('API_BASE_URL', 'http://backend:8000/api/v1');
} else {
    // Change port below if needed (default: 8002)
    define('API_BASE_URL', 'http://localhost:8002/api/v1');
}
