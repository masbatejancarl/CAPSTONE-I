<?php
// Shared base URL. Empty on Railway; set BASE_URL env var locally if needed (e.g. "/CAPSTONE%20I").
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));
}