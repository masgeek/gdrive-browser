# Google Drive Browser

This project is a Google Drive Browser built with PHP. It allows users to browse and interact with their Google Drive files and folders through a web interface.

## Features

- Browse Google Drive folders and files
- View file details and open files in Google Drive
- Navigate through folder breadcrumbs
- Caching of folder contents and breadcrumbs for improved performance

## Requirements

- PHP 8.1 or higher
- Composer
- Google API Client Library for PHP
- Whoops error handling library
- Symfony Cache component
- vlucas/phpdotenv for environment variable management

## Installation

1. Clone the repository:

```sh
git clone https://github.com/masgeek/gdrive-browser.git
cd gdrive-browser
```

## Configuration

Create a `.env` file in the root directory of the project and add the following environment variables:

```markdown
GOOGLE_CREDENTIALS_PATH=path/to/your/credentials.json
GOOGLE_SERVICE_ACCOUNT=your-service-account-email
GOOGLE_DEFAULT_FOLDER_ID=your-default-folder-id
GOOGLE_APPLICATION_NAME=your-application-name
