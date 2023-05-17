### build.config.json

```json
{
  "name": "PackageName",
  "version": "1.0.0",
  "projectReferences": [
    "relative/path/to/depend/project.json"
  ],
  "packageSources": [
    "https://phnet-registry"
  ],
  "packageReferences": {
    "PackageName": "1.23"
  },
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
  "pattern": "/include files pattern/",
  "exclude": [
    "/\/dirname\/.+/"
  ],
  "folders": [
    "relative/folder/path",
    {
      "source": "relative/source/path",
      "target": "target/path"
    }
  ],
  "buildFolders": [
    "relative/build/folder/path"
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
          "path": "path/to/file.extension"
        }
      ]
    }
  ]
}
```