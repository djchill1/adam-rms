# AdamRMS

## Key Changes

- **Global Check In** – Check assets back in across multiple projects from a single scan workflow.
- **Bulk Stock Handling** – Automatically replace bulk assets, such as cables, during dispatch scanning.
- **Local Logo Storage** – Store and render document logos locally without requiring S3.
- **Clone Assets Between Projects** – Copy assets from existing projects for repeat jobs and similar kit lists.
- **Browser-Based Scanning** – Remove the dependency on local hardware camera access for barcode scanning.
- **Scan Sound Effects** – Play distinct success and error sounds to provide immediate scanning feedback.

## Contributing

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?ref=main&repo=217888995)

Contributions are very welcome! Please see the [contributing guide](https://adam-rms.com/contributing) for full details.

### Development Environment

This repo includes a pre-configured [devcontainer](https://code.visualstudio.com/docs/devcontainers/tutorial) that sets up everything you need:

| Service | Port | Purpose |
|---|---|---|
| PHP/Apache | 8080 | Application server |
| MySQL 8.0 | 3306 | Database |
| S3 Mock | 8081 | Local file storage emulation |
| phpMyAdmin | 8082 | Database admin UI |
| Mailpit | 8083 | Email testing (captures all outbound mail) |

**Getting started:**

1. **GitHub Codespaces** (recommended) — click the badge above to launch a ready-to-use cloud environment
2. **VS Code** — clone the repo, open it in VS Code, and use the "Reopen in Container" command

The devcontainer will automatically install dependencies, run database migrations, and seed test data.

## License

AdamRMS is licensed under the [GNU Affero General Public License v3.0](LICENSE).
