### build.config.json

```json
{
  "name": "PackageName",
  "version": "1.0.0",
  // not required, default all php files
  "build": [ 
    // see include variations
    {
      "include": "*.php",
      "exclude": [
        "dirname/*.grv.php"
      ]
    }
  ],
  "requiredModules": [
    "gd",
    "phar"
  ],
  "projectReferences": [
    "relative/path/to/depend/project.json"
  ],
  "packageReferences": {
    "PackageName": "1.23.*"
  },
  "externalReferences": [
    {
      "type": "github",
      "owner": "owner",
      "repo": "repo",
      "version": "1.2.*"
    },
    {
      "type": "github",
      "owner": "owner",
      "repo": "repo",
      // if not phnet project, by analogy proj.jso
      "proj": {
        "name": "PackageName",
        "version": "1.0.0",
        "exclude": [
          "/exclude/"
        ]
      }
    }
  ],
  "include-php": [
    // see include variations
  ],
  "files": [
    // see include variations
  ],
  "resources": [
    // see include variations
  ],
  "sources": [
    "https://register.com"
  ],
  "entrypoint": [
    "Entrypoint\\Class",
    "entrypointMethod"
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

## Include variations:

use in build, files, resources, include-php

fn pattern:
```json
{
  "include": "path/*",
  "exclude": [
    "path/*.css"
  ]
}
```

regex pattern:
```json
 {
      "include": "/path\/./",
      "exclude": [
        "/path\/.+\\.css/"
      ]
    }
```