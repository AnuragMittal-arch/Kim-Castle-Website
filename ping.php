<?php
/* Temporary diagnostic: confirms whether POST reaches PHP on this site.
   Safe to delete once the opt-in form is confirmed working. */
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'method' => $_SERVER['REQUEST_METHOD'] ?? '?']);
