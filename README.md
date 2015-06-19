gp-import-export
===========

## Description

Bulk Import/Export plugin for GlotPress.

### Import sequence

The Import takes place in a sequence:

1. Upload a ZIP file containing `.po` files (they can be in subdirectories but cannot have the same name between the directories)
![step1](docs/import-step1.png)

2. Assign the locales to each `.po` file (this is done automatically if the file ends in `-locale`)
![step2](docs/import-step2.png)

3. Decide what should happen with the new translations
![step3](docs/import-step3.png)

4. Import done

### Export

![export](docs/export.png)

## TODOs
* Option to set importing user 
