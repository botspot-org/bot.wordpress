#!/usr/bin/env python3
"""
Simple JSON-LD test server for BotDot WP plugin testing

Serves random JSON-LD at any path ending with .json
"""

from flask import Flask, jsonify, request
from flask_cors import CORS
import random
from datetime import datetime

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Sample JSON-LD templates
ARTICLE_SCHEMA = {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Sample Article",
    "author": {
        "@type": "Person",
        "name": "John Doe"
    },
    "datePublished": datetime.now().isoformat(),
    "description": "This is a sample article for testing JSON-LD injection"
}

WEBPAGE_SCHEMA = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Sample Web Page",
    "description": "A sample web page with JSON-LD structured data",
    "url": "https://example.com"
}

BREADCRUMB_SCHEMA = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://example.com"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "Category",
            "item": "https://example.com/category"
        }
    ]
}

ORGANIZATION_SCHEMA = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "BotDot Test Organization",
    "url": "https://botdot.ai",
    "logo": "https://botdot.ai/logo.png",
    "sameAs": [
        "https://twitter.com/botdot",
        "https://github.com/botdot"
    ]
}

PRODUCT_SCHEMA = {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "Sample Product",
    "description": "A great product for testing",
    "offers": {
        "@type": "Offer",
        "price": "99.99",
        "priceCurrency": "USD",
        "availability": "https://schema.org/InStock"
    },
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.5",
        "reviewCount": "42"
    }
}

# List of all schemas to randomly choose from
SCHEMAS = [
    ARTICLE_SCHEMA,
    WEBPAGE_SCHEMA,
    BREADCRUMB_SCHEMA,
    ORGANIZATION_SCHEMA,
    PRODUCT_SCHEMA
]


@app.route('/<path:path>.json')
def serve_json_ld(path):
    """Serve JSON-LD for any path ending with .json"""

    # Choose a random schema or combine multiple
    num_schemas = random.randint(1, 3)
    selected_schemas = random.sample(SCHEMAS, num_schemas)

    # If multiple schemas, return as array
    if len(selected_schemas) > 1:
        response_data = selected_schemas
    else:
        response_data = selected_schemas[0]

    # Add path information to the response
    if isinstance(response_data, list):
        for schema in response_data:
            schema['_requested_path'] = f"/{path}"
            schema['_timestamp'] = datetime.now().isoformat()
    else:
        response_data['_requested_path'] = f"/{path}"
        response_data['_timestamp'] = datetime.now().isoformat()

    print(f"[{datetime.now().strftime('%H:%M:%S')}] Served JSON-LD for: /{path}.json")

    return jsonify(response_data)


@app.route('/<path:path>')
def serve_appendix(path):
    """Serve the 'appendix' - same JSON-LD without .json extension

    In the real BotDot system, this would serve JSON that the WP plugin
    fetches and renders as HTML within the page.
    """

    # Same logic as .json endpoint but typically less schemas
    num_schemas = random.randint(1, 2)
    selected_schemas = random.sample(SCHEMAS, num_schemas)

    if len(selected_schemas) > 1:
        response_data = selected_schemas
    else:
        response_data = selected_schemas[0]

    # Add metadata
    if isinstance(response_data, list):
        for schema in response_data:
            schema['_requested_path'] = f"/{path}"
            schema['_timestamp'] = datetime.now().isoformat()
            schema['_type'] = 'appendix'
    else:
        response_data['_requested_path'] = f"/{path}"
        response_data['_timestamp'] = datetime.now().isoformat()
        response_data['_type'] = 'appendix'

    print(f"[{datetime.now().strftime('%H:%M:%S')}] Served appendix JSON for: /{path}")

    return jsonify(response_data)


@app.route('/')
def index():
    """Root endpoint with instructions"""
    return jsonify({
        "message": "BotDot WP Test Server",
        "instructions": {
            "json_ld": "Request any path with .json extension to get JSON-LD",
            "appendix": "Request any path without .json to get appendix",
            "examples": [
                "/blog/my-post.json",
                "/products/item-123.json",
                "/about.json"
            ]
        },
        "timestamp": datetime.now().isoformat()
    })


if __name__ == '__main__':
    print("=" * 60)
    print("BotDot WP JSON-LD Test Server")
    print("=" * 60)
    print("\nStarting server on http://localhost:5000")
    print("\nExample requests:")
    print("  curl http://localhost:5000/blog/my-post.json")
    print("  curl http://localhost:5000/products/item-123.json")
    print("  curl http://localhost:5000/about.json")
    print("\nPress Ctrl+C to stop\n")
    print("=" * 60)

    app.run(host='0.0.0.0', port=5000, debug=True)
