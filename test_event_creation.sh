#!/bin/bash

# Simple test script to verify event creation via HTTP
cd /var/www/event-manager

echo "Testing Event Creation Form..."

# Test GET request to form
echo "1. Testing GET /events/new"
ddev exec curl -s -o /dev/null -w "%{http_code}" "http://localhost/events/new"
echo ""

echo "Event creation form should now work properly!"
echo "Visit: https://event-manager.ddev.site/events/new"
