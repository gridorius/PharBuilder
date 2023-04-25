### build.config.json 

```json
{
  "name": "PackageName",
  "version": "1.0.0",
  "projectReferences": [
    "relative/path/to/depend/project.json"
  ],
  "packageReferences": {
    "PackageName": "1.23"
  },
  "embeddedResources": [
    {
      "include": "path\/.",
      "exclude": [
        
      ]
    }
  ],
  "entrypoint": ["Entrypoint\\Class", "entrypointMethod"],
  "pattern": "/include files pattern/",
  "folders": [
    "relative/folder/path",
    {
      "source": "relative/source/path",
      "target": "target/path"
    }
  ],
  "files": [
    "relative/file/path.ext"
  ],
  "buildFolders": [
    "relative/build/folder/path"
  ]
}
```