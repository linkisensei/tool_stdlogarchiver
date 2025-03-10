# Logstore Archiver 🚀

The **Logstore Archiver** is a Moodle plugin that automates the archiving and cleanup of log records stored by _logstore_standard_. It groups records into CSV files and allows for data restoration, helping to keep the database lean and improve system performance.

## Features ✨

- **Log Archiving and Cleanup:** Groups a configurable number of records into CSV files and removes these records from the database.
- **Backup Restoration:** Allows you to restore archived records to _logstore_standard_ through adhoc tasks.
- **Backup Search:** Provides an interface to search and filter archived records by date, event, user, course, source, and other criteria.
- **Integration with External Services:** Offers the ability to synchronize backup files with external services, such as Amazon S3.  
  > ⚠️ **Note:** S3 integration is still under development and testing.

## Requirements 💻

- **Moodle:** Version 3.9 or later.
- **PHP:** Version 7.3 or later.

## Installation 🛠️

1. **Copy the Plugin:**  
   Copy the `stdlogarchiver` folder to the `admin/tool/` directory of your Moodle installation.
2. **Installation via Moodle:**  
   Log into Moodle as an administrator and follow the installation instructions provided.

## Configuration ⚙️

After installation, navigate to **Site Administration > Tools > Logstore Archiver** to configure the plugin:

- **Enable Plugin:** Activate the log archiver.
- **Records per File:** Set the maximum number of records to be grouped in each file (e.g., 5000, 10000, 25000, 50000, 100000).
- **Backup Format:** Currently, the only available format is CSV.
- **Log Retention Time:** Configure the period (in seconds) after which records become eligible for archiving (for example, 26 weeks).
- **External Backup (Amazon S3):**  
  Enter the configuration details (region, key, secret, bucket, and directory) for S3 integration.  
  > ⚠️ **Attention:** S3 integration is still under development and testing.
- **Delete Local Backup:** Enable this option to remove local backup files after synchronization with the external service.

## Usage 📂

- **Automatic Archiving:**  
  Scheduled tasks periodically archive and clean up log records, optimizing system performance.
- **Listing and Searching Backups:**  
  In the administrative area, you can:
  - View a list of generated backups, with options for download, restoration, or deletion.
  - Use the search interface to filter backup records by date, event, user, course, source, and more.
- **Restoration:**  
  Use the available restoration options to reimport archived records back into _logstore_standard_. This operation is carried out via ad hoc tasks.

## Development and Testing 🔬

The plugin includes a test suite that validates the archiving, restoration, and search functionalities. Configure your Moodle test environment as needed to run the tests.

## Contribution 🤝

Contributions are always welcome! To contribute:

1. Fork the repository.
2. Create a branch for your feature or bug fix.
3. Submit a pull request detailing your changes.

## License 📄

This plugin is distributed under the **MIT** license. Please refer to the license file for more details.

## Credits 🙏

- **Developer:** Lucas Barreto
- Inspired by and based on the functionalities of Moodle's _logstore_standard_.
