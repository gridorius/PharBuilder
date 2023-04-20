### build.config.json 

```json
{
  "name": "PackageName",
  "outName": "outPharName",
  "version": "1.0.0",
  "outDir": "relative/path/to/out/dir",
  "depends": [
    "relative/path/to/depend",
    "https://github.com/project_path.git"
  ],
  "bootScript": "boot script",
  "pattern": "/include files pattern/",
  "bootFile": "path to boot file",
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