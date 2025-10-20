#!/bin/bash

# Quick start script for BotDot WP test server

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}BotDot WP Test Server Setup${NC}"
echo "================================"
echo ""

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is required but not found"
    exit 1
fi

# Check if pip is installed
if ! command -v pip3 &> /dev/null; then
    echo "Error: pip3 is required but not found"
    exit 1
fi

# Install dependencies if needed
if [ ! -d ".venv" ]; then
    echo -e "${YELLOW}Creating virtual environment...${NC}"
    python3 -m .venv .venv
fi

echo -e "${YELLOW}Activating virtual environment...${NC}"
source .venv/bin/activate

echo -e "${YELLOW}Installing dependencies...${NC}"
uv sync

echo ""
echo -e "${GREEN}Starting test server...${NC}"
echo ""

# Run the server
python3 test-server.py
