#!/bin/bash

mkdir -p dist

zip -r dist/phpsleekdbadmin.zip phpsleekdbadmin.php phpsleekdbadmin.config.sample.php phpsleekdbadmin_dependencies

echo "DONE!"
