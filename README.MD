### build.config.json

```json
{
  "name": "PackageName",
  "version": "1.0.0",
  "projectReferences": [
    "relative/path/to/depend/project.json"
  ],
  "packageReferences": {
    "PackageName": "1.23.*"
  },
  "include-php": [
    "helpers.php"
  ],
  "files": [
    {
      "include": "/path\/./",
      "exclude": [
        "/path\/.+\\.css/"
      ]
    }
  ],
  "resources": [
    {
      "include": "/path\/./",
      "exclude": [
        "/path\/.+\\.css/"
      ]
    }
  ],
  "entrypoint": [
    "Entrypoint\\Class",
    "entrypointMethod"
  ],
  "exclude": [
    "/\/dirname\/.+/"
  ],
  "targets": [
    {
      "name": "compile scss",
      "tasks": [
        {
          "type": "Command",
          "command": "scss file.scss $(buildDirectory)/css/style.css"
        },
        {
          "type": "Copy",
          "from": "path",
          "to": "path"
        },
        {
          "type": "Delete",
          "path": "path/to/file"
        }
      ]
    }
  ]
}
```