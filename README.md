# REST API Log

**Contributors:** [gungeekatx](https://profiles.wordpress.org/gungeekatx/)  
**Tags:** wp rest api, rest api, wp

## Description

The REST API Log plugin provides comprehensive logging for WordPress REST API requests and responses. It allows you to monitor and analyze the interactions with your REST API endpoints, making it easier to debug and optimize your API.

## Features

- Log all REST API requests and responses
- View detailed information about each request, including headers, body, and response
- Filter logs by date, endpoint, status, and more
- Export logs to JSON format
- Custom endpoints functionality
- Custom logging functionality
- ElasticPress integration
- Settings management
- Unit tests for new functionality

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wp-rest-api-log` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the 'REST API Log' menu item in the WordPress admin to view and manage your logs.

## Usage

### Viewing Logs

To view the logs, navigate to the 'REST API Log' menu item in the WordPress admin. You can filter the logs by date, endpoint, status, and more. Click on a log entry to view detailed information about the request and response.

### Exporting Logs

To export logs, click the 'Export' button on the logs screen. You can choose to export the logs in JSON format.

### Custom Endpoints

The plugin allows you to create custom REST API endpoints. To create a custom endpoint, navigate to the 'Custom Endpoints' submenu under the 'REST API Log' menu. Click 'Add New' and fill in the required information, including the route, method, callback, and triggers.

### Custom Logging

The plugin provides custom logging functionality for selective logging of custom endpoints. You can configure the custom logging settings in the 'Settings' submenu under the 'REST API Log' menu.

### ElasticPress Integration

The plugin integrates with ElasticPress to log ElasticPress queries and handle sync requests. You can configure the ElasticPress settings in the 'Settings' submenu under the 'REST API Log' menu.

## Settings

The plugin provides various settings to customize its behavior. To access the settings, navigate to the 'Settings' submenu under the 'REST API Log' menu. The settings are organized into different sections, including General, ElasticPress, and Routes.

## Unit Tests

The plugin includes unit tests to ensure the functionality of the new features. The tests are located in the `tests` directory. To run the tests, use the following command:

```
phpunit
```

## Changelog

### 1.0.0
- Initial release

### 1.1.0
- Added error handling for failed script registrations
- Added validation for endpoint callback code
- Reviewed and uncommented code in `register_rest_routes` method
- Added error handling for dynamic endpoint registration
- Added error handling for database errors during log insertion
- Added error handling for failed log insertions
- Added validation for post data before loading
- Added error handling for invalid route filters
- Added error handling for text domain loading errors
- Added error handling for post type registration
- Added error handling for taxonomy registration errors

### 1.2.0
- Refactored `delete_items` method to work with `$_REQUESTs`
- Implemented `get_items`, `get_item`, `purge_log`, `batch_purge_log`, and `download_json` methods
- Fully implemented and tested custom endpoints functionality
- Verified custom logging functionality
- Reviewed and completed ElasticPress integration
- Reviewed and ensured all necessary settings are implemented and functional
- Added unit tests for new functionality
- Updated documentation to reflect the completed implementation

## License

This plugin is licensed under the GPLv2 or later license. For more information, see the LICENSE file.

## Credits

This plugin was developed by [gungeekatx](https://profiles.wordpress.org/gungeekatx/).
