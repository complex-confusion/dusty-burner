#!/bin/bash

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install
fi

# Build the React app
echo "Building React components..."
npm run build

echo "Build complete! React components are ready."